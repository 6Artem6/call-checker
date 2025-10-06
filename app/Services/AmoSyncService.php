<?php

namespace App\Services;

use App\Models\AiLead\Account\AccountOAuth2;
use App\Models\AiLead\Pipeline\LeadPipelineStatus;
use App\Models\AiLead\Pipeline\Pipeline;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AmoSyncService
{
    public function __construct(
        private readonly WebhookService $webhookService
    ) {
        ini_set('max_execution_time', 600);
        ini_set('memory_limit' , '-1');
    }

    public function syncAccount(int $accountId): void
    {
        $account = AccountOAuth2::findOrFail($accountId);

        if ($account->isTokenExpired()) {
            $account->refreshAccessData();
        }

        // 1. Забираем воронки
        $response = Http::withToken($account->access_token)
            ->baseUrl('https://' . $account->domain)
            ->get('/api/v4/leads/pipelines');

        if (!$response->successful()) {
            Log::channel('amocrm')->error("Ошибка получения воронок: " . $response->body());
            return;
        }

        $pipelines = $response->json()['_embedded']['pipelines'] ?? [];
        if (empty($pipelines)) {
            Log::channel('amocrm')->info("У аккаунта {$accountId} нет воронок");
            return;
        }

        // 2. Сохраняем воронки через WebhookService
        $pipelinesPayload = array_map(function ($pipeline) use ($account) {
            $pipeline['account_id'] = $account->account_id;
            return $pipeline;
        }, $pipelines);

        $this->webhookService->handleWebhookPipelines($pipelinesPayload);

        // 3. По каждой воронке собираем лиды постранично
        foreach ($pipelines as $pipeline) {
            $this->fetchAndStoreLeads($account, $pipeline['id']);
        }
    }

    public function fetchAndStoreLeads(AccountOAuth2 $account, int $pipelineId): void
    {
        if ($account->isTokenExpired()) {
            $account->refreshAccessData();
        }

        $page = 1;
        $hasMore = true;

        while ($hasMore) {
            sleep(1);

            $response = Http::withToken($account->access_token)
                ->baseUrl('https://' . $account->domain)
                ->get('/api/v4/leads', [
                    'filter' => ['pipeline_id' => $pipelineId],
                    'limit' => 250,
                    'page' => $page,
                ]);

            if (!$response->successful()) {
                Log::channel('amocrm')->error("Ошибка получения лидов (pipeline_id=$pipelineId, page=$page): " . $response->body());
                break;
            }

            $leads = $response->json()['_embedded']['leads'] ?? [];
            if (empty($leads)) {
                $hasMore = false;
                break;
            }

            // Готовим payload для handleWebhookLeadStatus
            $leadsPayload = array_map(fn($lead) => [
                'id'          => $lead['id'],
                'pipeline_id' => $pipelineId,
                'status_id'   => $lead['status_id'],
            ], $leads);

            $this->webhookService->handleWebhookLeadStatus($leadsPayload, $account->account_id);

            $page++;
        }
    }

    public function fetchAndStoreLead(AccountOAuth2 $account, int $leadId): ?int
    {
        // Берём текущую запись для лида
        $status = LeadPipelineStatus::where('lead_id', $leadId)->first();
        $statusId = $status?->status_id;
        $pipelineId = $status?->pipeline_id;

        // if (is_null($status)) {
            // Если записи нет — тянем сам лид
            $response = Http::withToken($account->access_token)
                ->baseUrl('https://' . $account->domain)
                ->get("/api/v4/leads", [
                    'filter' => ['id' => $leadId],
                ]);

            if ($response->successful()) {
                $leadData = $response->json()['_embedded']['leads'][0] ?? null;

                if ($leadData) {
                    $payload = [[
                        'id' => $leadData['id'],
                        'pipeline_id' => $leadData['pipeline_id'],
                        'status_id' => $leadData['status_id'],
                    ]];

                    $this->webhookService->handleWebhookLeadStatus($payload, $account->account_id);
                    $statusId = $leadData['status_id'];
                    $pipelineId = $leadData['pipeline_id'];

                    // проверяем, существует ли pipeline в локальной БД
                    if (!Pipeline::where('id', $pipelineId)->exists()) {
                        $this->syncPipelinesOnly($account);
                    }
                }
            } else {
                Log::channel('amocrm')->error("Не удалось получить lead_id={$leadId}: " . $response->body());
            }
        // } else {
            // $statusId = $status->status_id;

            // Проверка pipeline_id
            if (!Pipeline::where('id', $pipelineId)->exists()) {
                $this->syncPipelinesOnly($account);
            }
        // }

        return $statusId;
    }

    /**
     * Забираем только воронки и этапы, без лидов
     */
    protected function syncPipelinesOnly(AccountOAuth2 $account): void
    {
        $response = Http::withToken($account->access_token)
            ->baseUrl('https://' . $account->domain)
            ->get('/api/v4/leads/pipelines');

        if (!$response->successful()) {
            Log::channel('amocrm')->error("Ошибка получения воронок (syncPipelinesOnly): " . $response->body());
            return;
        }

        $pipelines = $response->json()['_embedded']['pipelines'] ?? [];
        if (empty($pipelines)) {
            Log::channel('amocrm')->info("У аккаунта {$account->account_id} нет воронок");
            return;
        }

        // Сохраняем воронки через WebhookService
        $pipelinesPayload = array_map(function ($pipeline) use ($account) {
            $pipeline['account_id'] = $account->account_id;
            return $pipeline;
        }, $pipelines);

        $this->webhookService->handleWebhookPipelines($pipelinesPayload);
    }
}
