<?php

namespace App\Services\OpenAI;

use App\Models\AiLead\Chat\UserChatThread;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIRunCancelService
{
    protected string $apiKey;
    protected string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->apiUrl = config('services.openai.api_url');
    }

    protected function getRequestBase()
    {
        return Http::baseUrl($this->apiUrl)
            ->withToken($this->apiKey)
            ->withHeaders([
                'OpenAI-Beta' => 'assistants=v2',
                'Content-Type' => 'application/json',
            ])
            ->timeout(30);
    }

    public function cancelActiveRunsForAllThreads(): void
    {
        // Получаем список threads — в API openai может не быть общего списка,
        // тогда сюда передаем массив thread_id вручную
//        $threads = $this->getThreads();

        $threads = UserChatThread::pluck('thread_id')->all();

        foreach ($threads as $threadId) {
            if (!$threadId) continue;
            $runs = $this->getRunsForThread($threadId);

            foreach ($runs as $run) {
//                Log::info(json_encode($run));
//                if (($run['status'] ?? '') === 'in_progress') {
                    $this->cancelRun($threadId, $run['id']);
                    Log::info("Cancelled run {$run['id']} in thread {$threadId}");
//                }
            }
        }
    }

    protected function getRunsForThread(string $threadId): array
    {
        $response = $this->getRequestBase()->get("/threads/{$threadId}/runs");

//        Log::info($response->json());
        if ($response->successful()) {
            return $response->json('data');
        }

        return [];
    }

    protected function cancelRun(string $threadId, string $runId): void
    {
        $response = $this->getRequestBase()->post("/threads/{$threadId}/runs/{$runId}/cancel");

        Log::info($response->json());
        if (!$response->successful()) {
            Log::error("Failed to cancel run {$runId}: " . $response->body());
        }
    }
}
