<?php

namespace App\Services;

use App\Models\ChatGPTSetting;
use App\Models\ChatMessage;
use App\Models\Request;
use App\Models\UserChatThread;
use App\Models\UserRequestThread;
use App\Models\UserThread;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\ConnectException;
use Exception;


class OpenAIAnalysisService
{
    protected string $api_key;
    protected string $api_url;
    protected string $assistant_id;
    protected string $thread_id;
    protected ?ChatGPTSetting $setting = null;

    public function __construct()
    {
        $this->api_key = config('services.openai.api_key');
        $this->api_url = config('services.openai.api_url');
    }

    protected function getHeaders(): array
    {
        return [
            'OpenAI-Beta' => 'assistants=v2',
            'Content-Type' => 'application/json',
        ];
    }

    protected function getRequestBase()
    {
        return Http::baseUrl($this->api_url)
            ->withToken($this->api_key)
            ->withHeaders($this->getHeaders())
            ->timeout(600);
    }

    public function setSetting(ChatGPTSetting $setting): void
    {
        $this->setting = $setting;
    }

    private function getOrCreateThreadId($type, array $params): string
    {
        $record = $type::query()->where($params)->first();

        if ($record && !is_null($record->thread_id)) {
            $response = $this->getRequestBase()
                ->post("/threads/{$record->thread_id}");
            if ($response->successful()) {
                return $record->thread_id;
            }
        }

        $data = $this->getRequestBase()
            ->post('/threads', [
                'tool_resources' => [
                    'file_search' => [
                        'vector_store_ids' => [$this->setting?->vector_store_id]
                    ]
                ]
            ])
            ->json();
        Log::info(json_encode($data, JSON_PRETTY_PRINT));
        $thread_id = $data['id'];
        $type::updateOrInsert($params, [
            'thread_id' => $thread_id,
            'status' => true
        ]);
        return $thread_id;
    }

    public function setParamsByUserRequest(Request $request): void
    {
        $this->assistant_id = config('services.openai.voice_assistant_id');
        $this->thread_id = $this->getOrCreateThreadId(
            UserRequestThread::class, [
                'user_id' => $request->user_id,
                'theme_id' => $request->theme_id
            ]
        );
    }

    public function setThreadByUserChat(ChatMessage $message): void
    {
        $this->assistant_id = config('services.openai.chat_assistant_id');
        $this->thread_id = $this->getOrCreateThreadId(
            UserChatThread::class, [
                'domain' => $message->domain,
                'lead_id' => $message->lead_id
            ]
        );
    }

    private function createRunRequest(string $content, array $params): array
    {
        try {
            // Проверяем, есть ли активный run в потоке
            $response = $this->getRequestBase()
                ->get("/threads/{$this->thread_id}/runs");
            $activeRun = collect($response->json('data'))
                ->firstWhere('status', 'in_progress');
            $run_id = $activeRun['id'] ?? null;
        } catch (ConnectException $e) {
            $run_id = null;
        }

        // Если активный run найден, ждем его завершения (максимум 60 секунд)
        if ($run_id) {
            $maxWaitTime = 60; // максимальное время ожидания в секундах
            $start = time();

            // Используем стрим для проверки статуса в реальном времени
            $statusResponse = $this->getRequestBase()
                ->get("/threads/{$this->thread_id}/runs/{$run_id}", [
                    'stream' => true
                ]);

            // Обрабатываем стрим
            $status = 'in_progress';
            while ($status === 'in_progress' && (time() - $start) < $maxWaitTime) {
                $statusResponse->stream(function ($chunk) use (&$status) {
                    // Каждый кусок данных, который поступает
                    $responseData = json_decode($chunk, true);
                    if (isset($responseData['status'])) {
                        $status = $responseData['status']; // Обновляем статус
                    }
                });
                sleep(2); // Пауза, чтобы не перегружать сервер
            }
        }

        // Пытаемся добавить сообщение в поток, повторяя попытку при ошибке о существующем активном run
        $messageResponse = null;
        $retryCount = 0;
        $maxRetries = 3;

        while ($retryCount < $maxRetries) {
            try {
                $messageResponse = $this->getRequestBase()
                    ->post("/threads/{$this->thread_id}/messages", [
                        'role' => 'user',
                        'content' => $content,
                    ]);
                // Если запрос успешен, выходим из цикла
                break;
            } catch (Exception $e) {
                // Если ошибка указывает на активный run, ждем и повторяем попытку
                if (str_contains($e->getMessage(), 'already has an active run')) {
                    sleep(2);
                    $retryCount++;
                } else {
                    // Если ошибка другого типа, выбрасываем её дальше
                    throw $e;
                }
            }
        }

        Log::info(json_encode($messageResponse->json(),
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // Если активного run не было, создаём новый run
        if (!$run_id) {
            return $this->getRequestBase()
                ->post("/threads/{$this->thread_id}/runs", $params)
                ->json();
        }

        // Возвращаем результат добавления сообщения
        return $messageResponse->json();
    }

    public function analysisFunction(string $content, ?string $function_name,
                                     string $function_output, array $instruction_list = [])
    {
        Log::info("Running analysis function");

        $run = $this->createRunRequest($content, [
            'assistant_id' => $this->assistant_id,
            'additional_instructions' => implode("\n", $instruction_list),
            'model' => "gpt-4o",
            'tools' => $function_name ? [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => $function_name
                    ]
                ]
            ] : null,
        ]);

        if (!isset($run['id'])) {
            return ['', false];
        }

        [$run, $messageId] = $this->threadWait($run['id']);
        $result = $this->getResult($run, $messageId);

        $this->submitFunctionOutputs($run, $function_name, $function_output);
        [$run, $messageId] = $this->threadWait($run['id']);

        return $this->getResult($run, $messageId);
    }

    public function chatFunction(string $content)
    {
        Log::info("Running chat function");

        $run = $this->createRunRequest($content, [
            'assistant_id' => $this->setting->assistant_id,
//            'additional_instructions' => $this->setting->prompt,
            'model' => $this->setting->model,
            'temperature' => (float) $this->setting->temperature,
            'tools' => [['type' => 'file_search']],
            'tool_resources' => [
                'file_search' => [
                    'vector_store_ids' => [$this->setting->vector_store_id]
                ]
            ],
        ]);

        if (!isset($run['id'])) {
            Log::info(json_encode($run, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return ['', false];
        }

        [$run, $messageId] = $this->threadWait($run['id']);
        return $this->getResult($run, $messageId);
    }

    // Проверка статуса Run
    public function isRunInProgress(string $status): bool
    {
        return in_array($status, ['queued', 'in_progress', 'cancelling']);
    }

    // Получение данных выполнения
    public function getRun(string $runId)
    {
        return $this->getRequestBase()
            ->get("/threads/{$this->thread_id}/runs/{$runId}")
            ->json();
    }

    // Получение шагов Run
    public function getRunSteps(string $runId)
    {
        return $this->getRequestBase()
            ->get("/threads/{$this->thread_id}/runs/{$runId}/steps")
            ->json('data', []);
    }

    // Получение данных Run
    public function retrieveRun(string $runId)
    {
        return $this->getRequestBase()
            ->get("/threads/{$this->thread_id}/runs/{$runId}")
            ->json();
    }

    // Отмена Run
    public function cancelRun(string $runId): void
    {
        $this->getRequestBase()
            ->post("/threads/{$this->thread_id}/runs/{$runId}/cancel");
    }

    // Получение результата из Run
    public function getResult(?array $run, ?string $messageId): array
    {
        $result = '';
        Log::info(json_encode($run, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        if (!empty($run) and ($run['status'] === 'completed')) {
            $messages = $this->getMessages();
            if (!empty($messages)) {
                $result = $messages[0]['content'][0]['text']['value'] ?? '';
            }
        } elseif ($messageId) {
            $message = $this->getMessageById($messageId);
            $result = $message['content'][0]['text']['value'] ?? '';
        }

        return [$result, !empty($result)];
    }

    // Получение всех сообщений из Run
    public function getMessages()
    {
        return $this->getRequestBase()
            ->get("/threads/{$this->thread_id}/messages")
            ->json('data', []);
    }

    // Получение конкретного сообщения по ID
    public function getMessageById(string $messageId)
    {
        return $this->getRequestBase()
            ->get("/threads/{$this->thread_id}/messages/{$messageId}")
            ->json();
    }

    // Ожидание завершения выполнения
    public function threadWait(string $runId): array
    {
        $messageId = null;
        $stop = false;
        $run = null;
        while (!$stop) {
            sleep(1);
            $steps = $this->getRunSteps($runId);

            Log::info(json_encode($steps, JSON_PRETTY_PRINT));
            foreach ($steps as $step) {
                if ($step['status'] === 'completed') {
                    $stop = true;
                    break;
                }
            }

            if ($stop) {
                $messageId = $steps[0]['step_details']['message_creation']['message_id'] ?? null;
            } else {
                $run = $this->getRun($runId);
            }
        }

        return [$run, $messageId];
    }

    // Отправка выходных данных для функции
    public function submitFunctionOutputs(array $run, ?string $functionName, string $functionOutput): bool
    {
        $toolOutputs = [];
        if (!is_null($functionName)) {
            foreach ($run['required_action']['submit_tool_outputs']['tool_calls'] as $tool) {
                if ($tool['function']['name'] === $functionName) {
                    $toolOutputs[] = [
                        "tool_call_id" => $tool['id'],
                        "output" => $functionOutput,
                    ];
                }
            }
        }

        $status = true;
        if (!empty($toolOutputs)) {
            $run_id = $run['id'];
            try {
                $this->getRequestBase()
                    ->post("/threads/{$this->thread_id}/runs/{$run_id}/submit_tool_outputs", [
                        'tool_outputs' => $toolOutputs,
                    ]);
            } catch (Exception) {
                // Логирование ошибки или обработка
                $status = false;
            }
        }

        return $status;
    }
}
