<?php

namespace App\Services\OpenAI\Abstracts;

use App\Models\AiLead\Chat\UserChatThread;
use App\Models\AiLead\Gpt\Abstracts\BaseChatGPTSetting;
use App\Models\AiLead\Chat\Abstracts\BaseChatMessage;
use App\Models\AiLead\Payment\TokenUsage;
use App\Models\AiLead\Payment\Transaction;
use App\Models\Voice\UserRequestThread;
use App\Models\AiLead\Chat\PlaygroundChatThread;
use App\Services\ModelPricingService;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\ConnectException;
use Exception;


abstract class AbstractOpenAIService
{
    protected string $api_key;
    protected string $api_url;
    protected ?string $assistant_id = null;
    protected ?string $thread_id = null;
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

    protected function getGuzzleClient(): Client
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

    public function setParamsByUserRequest(\App\Models\Voice\Request $request): void
    {
        $this->assistant_id = config('services.openai.voice_assistant_id');
        $this->thread_id = $this->getOrCreateThreadId(
            UserRequestThread::class, [
                'user_id' => $request->user_id,
                'theme_id' => $request->theme_id
            ]
        );
    }

    public function setThreadByUserChat(BaseChatMessage $message): void
    {
        $this->assistant_id = config('services.openai.chat_assistant_id');
        $this->thread_id = $this->getOrCreateThreadId(
            UserChatThread::class, [
                'domain' => $message->domain,
                'lead_id' => $message->lead_id
            ]
        );
    }

    public function setThreadByPlaygroundChat(BaseChatMessage $message): void
    {
        $this->chat_message = $message;
        $this->assistant_id = config('services.openai.chat_assistant_id');
        $this->thread_id = $this->getOrCreateThreadId(
            PlaygroundChatThread::class, [
                'account_id' => $this->chat_message->account_id
            ]
        );
    }

    /**
     * По возможности subclasses могут переопределить.
     */
    protected function updateMessageStatus(string $status): void
    {
        if ($this->chat_message) {
            $this->chat_message->status = $status;
            $this->chat_message->save();
        }
    }

    protected function getOrCreateThreadId($type, array $params): string
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

        Log::channel('amocrm')->info(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $thread_id = $data['id'];
        $type::updateOrInsert($params, [
            'thread_id' => $thread_id,
            'status' => true
        ]);
        return $thread_id;
    }

    /**
     * Создаёт/проверяет run, ждёт завершения активного run (polling), отправляет сообщение.
     * Возвращает JSON-ответ от POST /threads/{thread_id}/messages или /runs.
     *
     * @param string $content
     * @param array $params
     * @return array
     */
    protected function createRunRequest(string $content, array $params): array
    {
        $run_id = null;

        // 1) Попробуем найти активный run
        try {
            $response = $this->getRequestBase()->get("/threads/{$this->thread_id}/runs");
            $activeRun = collect($response->json('data'))->firstWhere('status', 'in_progress');
            $run_id = $activeRun['id'] ?? null;
            Log::channel('amocrm')->info("active run_id: {$run_id}");
        } catch (ConnectException $e) {
            Log::warning("Unable to list runs: " . $e->getMessage());
            $run_id = null;
        }

        // 2) Если есть активный run — опросим его статус (polling) до завершения или таймаута
        if ($run_id) {
            $attempts = 0;
            $maxAttempts = 30;
            $status = 'in_progress';
            $this->updateMessageStatus($status);

            while ($status === 'in_progress' && $attempts < $maxAttempts) {
                try {
                    $res = $this->getRequestBase()->get("/threads/{$this->thread_id}/runs/{$run_id}");
                    $data = $res->json();
                } catch (\Throwable $e) {
                    Log::warning("Error while polling run {$run_id}: " . $e->getMessage());
                    break;
                }

                if (isset($data['status'])) {
                    $status = $data['status'];
                    Log::channel('amocrm')->info("Run status: {$status}");
                    $this->updateMessageStatus($status);
                } else {
                    Log::warning('Run status not found in response for run ' . $run_id);
                    break;
                }

                if ($status === 'in_progress') {
                    sleep(2);
                }

                $attempts++;
            }

            // Вернём актуальные данные run (если удалось получить)
            try {
                $runData = $this->getRequestBase()->get("/threads/{$this->thread_id}/runs/{$run_id}")->json();
                return $runData;
            } catch (\Throwable $e) {
                Log::warning("Cannot retrieve run {$run_id} after polling: " . $e->getMessage());
                // продолжаем — можно попытаться создать новый run ниже
            }
        }

        // 3) Отправим сообщение в тред (это вернёт msg_... объект)
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
                Log::warning("Message send failed (attempt {$retryCount}): {$e->getMessage()}");
                sleep(1);
            }
            $retryCount++;
        }

        if (!$messageResponse || !method_exists($messageResponse, 'json')) {
            Log::error("Failed to send message after {$maxRetries} attempts.");
            return [];
        }

        $msg = $messageResponse->json();
        Log::channel('amocrm')->info("Message response: " . json_encode($msg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 4) Если в ответе на сообщение есть run_id — получим этот run и вернём его
        $msg_run_id = $msg['run_id'] ?? null;
        if (!empty($msg_run_id)) {
            try {
                $runData = $this->getRequestBase()->get("/threads/{$this->thread_id}/runs/{$msg_run_id}")->json();
                Log::channel('amocrm')->info("Found run for message: " . json_encode($runData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                return $runData;
            } catch (\Throwable $e) {
                Log::warning("Cannot retrieve run {$msg_run_id} referenced from message: " . $e->getMessage());
                // fallthrough -> попробуем создать run ниже
            }
        }

        // 5) Если run не найден из сообщения — создаём новый run напрямую и возвращаем его
        try {
            Log::channel('amocrm')->info("No run_id from message, creating new run...");
            $runResp = $this->getRequestBase()->post("/threads/{$this->thread_id}/runs", $params)->json();

            // Нормализуем: если API вернул контейнер ['run' => {...}], распакуем
            if (isset($runResp['run']) && is_array($runResp['run'])) {
                return $runResp['run'];
            }

            if (isset($runResp['id'])) {
                return $runResp;
            }

            // fallback — вернуть полный ответ
            return $runResp;
        } catch (\Throwable $e) {
            Log::error("Failed to create run: " . $e->getMessage());
            return [];
        }
    }

    public function isRunInProgress(string $status): bool
    {
        return in_array($status, ['queued', 'in_progress', 'cancelling']);
    }

    public function getRun(string $runId)
    {
        return $this->getRequestBase()
            ->get("/threads/{$this->thread_id}/runs/{$runId}")
            ->json();
    }

    public function getRunSteps(string $runId)
    {
        return $this->getRequestBase()
            ->get("/threads/{$this->thread_id}/runs/{$runId}/steps")
            ->json('data', []);
    }

    public function retrieveRun(string $runId)
    {
        return $this->getRequestBase()
            ->get("/threads/{$this->thread_id}/runs/{$runId}")
            ->json();
    }

    public function cancelRun(string $runId): void
    {
        $this->getRequestBase()
            ->post("/threads/{$this->thread_id}/runs/{$runId}/cancel");
    }

    public function getResult(?array $run, ?string $messageId): array
    {
        $result = '';
        Log::channel('amocrm')->info("\$run - " . json_encode($run, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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

    public function getMessages()
    {
        return $this->getRequestBase()
            ->get("/threads/{$this->thread_id}/messages")
            ->json('data', []);
    }

    public function getMessageById(string $messageId)
    {
        return $this->getRequestBase()
            ->get("/threads/{$this->thread_id}/messages/{$messageId}")
            ->json();
    }

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

            Log::channel('amocrm')->info("Run status: $status");
            if (in_array($status, ['completed', 'failed', 'cancelled', 'expired'])) {
                break;
            } else {
                Log::channel('amocrm')->info("\$run - " . json_encode($run, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }

        $steps = $this->getRunSteps($runId);
        Log::channel('amocrm')->info("steps:");
        Log::channel('amocrm')->info(json_encode($steps, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $messageId = $steps[0]['step_details']['message_creation']['message_id'] ?? null;

        return [$run, $messageId];
    }

    public function submitFunctionOutputs(array $run, ?string $functionName, string $functionOutput): bool
    {
        $toolOutputs = [];
        if (!is_null($functionName) && !empty($run['required_action']['submit_tool_outputs']['tool_calls'] ?? [])) {
            foreach ($run['required_action']['submit_tool_outputs']['tool_calls'] as $tool) {
                if (($tool['function']['name'] ?? '') === $functionName) {
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
                $status = false;
            }
        }

        return $status;
    }

    protected function storeUsage(array $usage, string $model, ?int $clientId = null): void
    {
        if (empty($usage)) {
            Log::channel('amocrm')->warning('empty($usage)');
            return;
        }

        $promptTokens     = $usage['prompt_tokens']    ?? ($usage['input_tokens']  ?? 0);
        $completionTokens = $usage['completion_tokens']?? ($usage['output_tokens'] ?? 0);
        $totalTokens      = $usage['total_tokens']     ?? ($promptTokens + $completionTokens);

        $pricingInfo = app(ModelPricingService::class)->getPricingForModel($model);

        if ($pricingInfo) {
            $usd = ($promptTokens / 1_000_000) * $pricingInfo['input']
                + ($completionTokens / 1_000_000) * $pricingInfo['output'];
        } else {
            Log::channel('amocrm')->warning("Цена не найдена для модели «{$model}»");
            $usd = 0.0;
        }

        $fx  = app(ModelPricingService::class)->getUsdRubRate();
        $rub = $usd * $fx;

        TokenUsage::create([
            'client_id'        => $clientId,
            'model'            => $model,
            'prompt_tokens'    => $promptTokens,
            'completion_tokens'=> $completionTokens,
            'total_tokens'     => $totalTokens,
            'usd_cost'         => $usd,
            'rub_cost'         => $rub,
            'fx_used'          => $fx,
            'margin_used'      => 1.0,
            'message_id'       => $messageId ?? null,
        ]);

        if ($clientId) {
            Transaction::create([
                'client_id'   => $clientId,
                'type'        => Transaction::TYPE_USAGE,
                'usd_cost'    => $usd,
                'fx_used'     => $fx,
                'margin_used' => 1.0,
                'message_id'  => $messageId ?? null,
                'status'      => Transaction::STATUS_COMPLETED,
            ]);
        }
    }
}
