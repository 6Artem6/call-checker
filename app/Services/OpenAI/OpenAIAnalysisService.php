<?php

namespace App\Services\OpenAI;

use App\Services\OpenAI\Abstracts\AbstractOpenAIService;
use App\Models\AiLead\Chat\{ChatMessage, ChatMessagePlayground};
use App\Models\AiLead\Chat\Abstracts\BaseChatMessage;
use App\Models\Voice\Request;
use Illuminate\Support\Facades\Log;

class OpenAIAnalysisService extends AbstractOpenAIService
{
    public function __construct()
    {
        parent::__construct();
    }

    public function setParamsByUserRequest(Request $request): void
    {
        parent::setParamsByUserRequest($request);
        $this->assistant_id = config('services.openai.voice_assistant_id');
    }

    public function setThreadByUserChat(BaseChatMessage $message): void
    {
        // если нужно, можно проверять конкретный класс
        if (!$message instanceof ChatMessage) {
            throw new \InvalidArgumentException('Expected ChatMessage');
        }
        parent::setThreadByUserChat($message);
    }

    public function setThreadByPlaygroundChat(BaseChatMessage $message): void
    {
        // если нужно, можно проверять конкретный класс
        if (!$message instanceof ChatMessagePlayground) {
            throw new \InvalidArgumentException('Expected ChatMessagePlayground');
        }
        parent::setThreadByPlaygroundChat($message);
    }

    public function analysisFunction(string $content, ?string $functionName, string $functionOutput, array $instructionList = [])
    {
        Log::channel('amocrm')->info("Running analysis function");
        $model = "gpt-4o";
        $run = $this->createRunRequest($content, [
            'assistant_id' => $this->assistant_id,
            'additional_instructions' => implode("\n", $instructionList),
            'model' => $model,
            'tools' => $functionName ? [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => $functionName
                    ]
                ]
            ] : null,
        ]);

        Log::channel('amocrm')->info("run - " . json_encode($run, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        if (!isset($run['id'])) {
            return ['', false];
        }

        [$run, $messageId] = $this->threadWait($run['id']);
        $result = $this->getResult($run, $messageId);

        $this->submitFunctionOutputs($run, $functionName, $functionOutput);
        [$run, $messageId] = $this->threadWait($run['id']);

        $usage = $run['usage'] ?? [];
        $this->storeUsage($usage, $model, auth()->user()->account_id);

        return $this->getResult($run, $messageId);
    }

    public function chatFunction(string $content): array
    {
        Log::channel('amocrm')->info("Running chat function");

        $run = $this->createRunRequest($content, [
            'assistant_id' => $this->setting->assistant_id,
            'model' => $this->setting->model,
            'tools' => [['type' => 'file_search']],
            'tool_resources' => [
                'file_search' => [
                    'vector_store_ids' => [$this->setting->vector_store_id]
                ]
            ],
        ]);

        Log::channel('amocrm')->info("run: " . json_encode($run, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        if (!isset($run['id'])) {
            return ['', false];
        }

        [$run, $messageId] = $this->threadWait($run['id']);
        $usage = $run['usage'] ?? [];
        
        $this->storeUsage($usage, $this->setting->model, $this->setting->account_id);

        return $this->getResult($run, $messageId);
    }
}
