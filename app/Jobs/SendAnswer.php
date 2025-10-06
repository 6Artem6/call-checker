<?php

namespace App\Jobs;

use App\Models\AiLead\Gpt\ChatGPTSetting;
use App\Models\AiLead\Chat\ChatMessage;
use Facebook\WebDriver\Exception\TimeoutException;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use PDOException;
use Throwable;

class SendAnswer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 3;

    public function backoff(): array
    {
        return [1, 5, 10];
    }

    public function __construct(
        protected int $messageId,
        protected int $settingId
    ) {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '-1');
    }

    public function handle(): void
    {
        try {
            // Вместо прямой обработки — ставим батч
            Log::channel('amocrm')->info("SendAnswer queued batch", [
                'message_id' => $this->messageId,
                'setting_id' => $this->settingId,
            ]);

            $message = ChatMessage::findOrFail($this->messageId);
            $setting = ChatGPTSetting::findOrFail($this->settingId);

            // Отложенная обработка пачки для лида
            ProcessBatchAnswer::dispatch(
                $message->lead_id,
                $this->settingId
            )->delay(now()->addSeconds($setting->delay));

        } catch (Throwable $e) {
            $this->failed($e);
            throw $e;
        }
    }

    public function failed(TimeoutException|ConnectException|PDOException|Throwable $exception): void
    {
        Log::channel('amocrm')->error("SendAnswer failed", [
            'message_id' => $this->messageId,
            'setting_id' => $this->settingId,
            'error'      => $exception->getMessage(),
        ]);

        try {
            $message = ChatMessage::find($this->messageId);
            $setting = ChatGPTSetting::with('account')->find($this->settingId);

            if ($message && $setting) {
                $message->setSetting($setting);
                $message->saveAnswerAnalysis();
            }
        } catch (Throwable $e) {
            Log::channel('amocrm')->error("Failed recovery also failed", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
