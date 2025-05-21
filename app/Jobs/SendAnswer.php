<?php

namespace App\Jobs;

use App\Models\ChatGPTSetting;
use App\Models\ChatMessage;
use Facebook\WebDriver\Exception\TimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAnswer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ChatMessage $message;
    protected ChatGPTSetting $setting;

    /**
     * Create a new job instance.
     */
    public function __construct(ChatMessage $message, ChatGPTSetting $setting)
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '-1');
        $this->message = $message;
        $this->setting = $setting;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
//        try {
        Log::info("before message");
        $this->message->setSetting($this->setting);
        $this->message->saveAnswerAnalysis();
        Log::info("after message");
//        } catch (TimeoutException) {
//            $this->message->saveAnswerAnalysis();
//        }
    }
}
