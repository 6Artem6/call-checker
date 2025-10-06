<?php

namespace App\Jobs;

use App\Models\AiLead\Gpt\ChatGPTSetting;
use App\Models\AiLead\Chat\ChatMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBatchAnswer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 3;

    public function __construct(
        protected int $leadId,
        protected int $settingId
    ) {}

    public function handle(): void
    {
        $setting = ChatGPTSetting::with('account')->findOrFail($this->settingId);

        // Берём все сообщения без ответа для этого лида
        $messages = ChatMessage::where('lead_id', $this->leadId)
            ->whereNull('has_reply')
            ->orderBy('id')
            ->get();

        if ($messages->isEmpty()) {
            Log::channel('amocrm')->info("Batch: нет сообщений для lead {$this->leadId}");
            return;
        }

        // Последнее сообщение для контекста
        $lastMessage = $messages->last();
        $lastMessage->setSetting($setting);

        // Запускаем анализ и создание ответа отдельным сообщением
        $lastMessage->saveAnswerAnalysis();

        // Отмечаем все сообщения как обработанные, ставим reply_id на сообщение бота
        // Оно уже будет создано внутри saveAnswerAnalysis
        $botMessage = ChatMessage::where('reply_id', $lastMessage->id)
            ->where('origin', 'bot')
            ->latest('id')
            ->first();

        if ($botMessage) {
            ChatMessage::whereIn('id', $messages->pluck('id')->all())
                ->whereNull('has_reply')
                ->update([
                    'has_reply' => true,
                    'reply_id' => $botMessage->id,
                ]);
        }

        Log::channel('amocrm')->info("Batch обработан", [
            'lead_id' => $this->leadId,
            'messages_count' => $messages->count(),
        ]);
    }
}
