<?php

namespace App\Services;

use App\Models\AiLead\Gpt\Abstracts\BaseChatGPTSetting;
use App\Models\AiLead\Chat\Abstracts\BaseChatMessage;
use App\Models\AiLead\Chat\{ChatMessage, ChatMessagePlayground, UserChatThread, PlaygroundChatThread};
use App\Models\Voice\{UserRequestThread, Request};
use GuzzleHttp\Client;
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
    protected ?BaseChatGPTSetting $setting = null;
    protected ?BaseChatMessage $chat_message = null;

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

    private function getGuzzleClient(): Client
    {
        return new Client([
            'base_uri' => $this->api_url,
            'timeout' => 600,
            'headers' => array_merge(
                ['Authorization' => "Bearer {$this->api_key}"],
                $this->getHeaders()
            ),
        ]);
    }

    public function setSetting(BaseChatGPTSetting $setting): void
    {
        $this->setting = $setting;
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

    public function setThreadByPlaygroundChat(ChatMessagePlayground $message): void
    {
        $this->chat_message = $message;
        $this->assistant_id = config('services.openai.chat_assistant_id');
        $this->thread_id = $this->getOrCreateThreadId(
            PlaygroundChatThread::class, [
                'account_id' => $this->chat_message->account_id
            ]
        );
    }

    private function updateMessageStatus(string $status): void
    {
        if ($this->chat_message) {
            $this->chat_message->status = $status;
            $this->chat_message->save();
        }
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

    private function createRunRequest(string $content, array $params): array
    {
        try {
            $response = $this->getRequestBase()
                ->get("/threads/{$this->thread_id}/runs");

            $activeRun = collect($response->json('data'))
                ->firstWhere('status', 'in_progress');

            $run_id = $activeRun['id'] ?? null;
            Log::info("run_id: {$run_id}");
        } catch (ConnectException $e) {
            Log::info(json_encode($e->getMessage(),
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $run_id = null;
        }

        if ($run_id) {
            Log::info("\$run_id - {$run_id}");

            $attempts = 0;
            $maxAttempts = 30;
            $status = 'in_progress';
            $this->updateMessageStatus($status);

            // Вместо стрима — делаем опрос статуса по таймауту
            while ($status === 'in_progress' && $attempts < $maxAttempts) {
                $res = $this->getRequestBase()
                    ->get("/threads/{$this->thread_id}/runs/{$run_id}");
                $data = $res->json();

                Log::info("res: {$res}");
                if (isset($data['status'])) {
                    $status = $data['status'];
                    Log::info("Run status: {$status}");

                    $this->updateMessageStatus($status);
                } else {
                    Log::warning('Run status not found in response');
                    break;
                }

                if ($status === 'in_progress') {
                    sleep(2);
                }

                $attempts++;
            }
        }

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
                break;
            } catch (Exception $e) {
                Log::info(json_encode($e->getMessage(),
                    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                if ($status === 'in_progress') {
                    Log::info("Waiting for run to complete...");
                    sleep(2);
                } elseif (!in_array($status, ['completed', 'requires_action', 'failed', 'cancelled', 'expired'])) {
                    Log::warning("Unexpected run status: {$status}");
                    break;
                } else {
                    throw $e;
                }
            }
        }

        Log::info(json_encode($messageResponse->json(),
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        if (!$run_id) {
            Log::info("!\$run_id = {$run_id}");

            return $this->getRequestBase()
                ->post("/threads/{$this->thread_id}/runs", $params)
                ->json();
        }

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
            // 'temperature' => (float) $this->setting->temperature,
            'tools' => [['type' => 'file_search']],
            'tool_resources' => [
                'file_search' => [
                    'vector_store_ids' => [$this->setting->vector_store_id]
                ]
            ],
        ]);

        if (!isset($run['id'])) {
            Log::info("run:");
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
        Log::info("run:");
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
        $run = null;

        $start = time();
        $timeout = 300; // 5 минут

        while (true) {
            sleep(1);

            if (time() - $start > $timeout) {
                Log::warning("Run $runId exceeded 5-minute timeout.");
                break;
            }

            $run = $this->getRun($runId);
            $status = $run['status'] ?? 'unknown';

            $this->updateMessageStatus($status);

            Log::info("Run status: $status");
            if (in_array($status, ['completed', 'failed', 'cancelled', 'expired'])) {
                break;
            }
        }

        $steps = $this->getRunSteps($runId);
        Log::info("steps:");
        Log::info(json_encode($steps, JSON_PRETTY_PRINT));

        $messageId = $steps[0]['step_details']['message_creation']['message_id'] ?? null;

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
