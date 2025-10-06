<?php

namespace App\Services;

use App\Jobs\SendAnswer;
use App\Models\AiLead\Account\AccountOAuth2;
use App\Models\AiLead\Gpt\{AccountGPTSetting, ChatGPTSetting};
use App\Models\AiLead\Chat\{ChatMessage, Schedule, UserChatThread};
use App\Models\AiLead\Pipeline\{Pipeline, PipelineStatus, LeadPipelineStatus};
use Illuminate\Support\Facades\Log;
use Throwable;


class WebhookService
{
    /**
     * Обработка вебхука сообщений (чаты AmoCRM).
     */
    public function handleMessageWebhook(array $validated): void
    {
        try {
            $data = $validated['message']['add'][0];

            if ($data['entity_type'] === 'lead') {
                $data['domain'] = parse_url($validated['account']['_links']['self'])['host'];

                $account = AccountOAuth2::where('domain', '=', $data['domain'])->first();
                if (!$account) {
                    Log::channel('amocrm')->warning('Account is not created');
                    return;
                }

                $data['lead_id'] = $data['entity_id'];

                $record = UserChatThread::where('domain', $data['domain'])
                    ->where('lead_id', $data['lead_id'])
                    ->first(['status']);

                if (!$record) {
                    $record = UserChatThread::create([
                        'domain' => $data['domain'],
                        'lead_id' => $data['lead_id'],
                        'status' => true,
                    ]);
                }

                $message = new ChatMessage();
                $message->fill($data);
                $message->save();

                if (!$account->is_active) {
                    Log::channel('amocrm')->warning('Account is not active');
                    return;
                }

                if (!$record->status) {
                    Log::channel('amocrm')->warning('Thread is not active');
                    return;
                }

                $leadPipelineStatus = LeadPipelineStatus::query()
                    ->where('lead_id', '=', $data['lead_id'])
                    ->first();
                $statusId = $leadPipelineStatus?->status_id;

                if (!$statusId) {
                    // проверяем каждый раз, потому что amo не шлёт update-webhook'и
                    $service = new AmoSyncService($this);
                    $statusId = $service->fetchAndStoreLead($account, $data['lead_id']);
                    if (!$statusId) {
                        Log::channel('amocrm')->warning('Pipeline Status ID не найден для этого лида');
                        return;
                    }
                }

                $accountSetting = AccountGPTSetting::query()
                    ->with('account')
                    ->with('setting')
                    ->where('pipeline_status_id', $statusId)
                    ->whereHas('account', function ($query) use ($data) {
                        $query->where('domain', $data['domain']);
                    })
                    ->first();

                if (!empty($accountSetting->setting)) {
                    if (!$accountSetting->setting->is_active) {
                        Log::channel('amocrm')->warning('Данная воронка неактивна.');
                        return;
                    }
                    if (!Schedule::isActiveNow($accountSetting->setting->setting_id)) {
                        Log::channel('amocrm')->warning('Бот неактивен в это время.');
                        return;
                    }
                    SendAnswer::dispatch($message->id, $accountSetting->setting->setting_id);
                    Log::channel('amocrm')->info('SendAnswer dispatched');
                } else {
                    Log::channel('amocrm')->warning("empty(\$accountSetting->setting)");
                    Log::channel('amocrm')->warning(AccountGPTSetting::query()
                        ->with('account')
                        ->with('setting')
                        ->where('pipeline_status_id', $statusId)
                        ->whereHas('account', function ($query) use ($data) {
                            $query->where('domain', $data['domain']);
                        })->toRawSql());
                }
            }
        } catch (Throwable $e) {
            Log::channel('amocrm')->error('Ошибка в обработке вебхука: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function handleWebhookPluginActivate(array $data): array
    {
        $record = AccountOAuth2::updateOrCreate(
            ['domain' => $data['domain']],
            ['account_id' => $data['account_id']]
        );

        $settings = ChatGPTSetting::createOrFirst(
            ['account_id' => $data['account_id']]
        );

        return ['status' => (!is_null($record) && !is_null($settings))];
    }

    public function handleWebhookPluginStatus(array $data): array
    {
        $record = AccountOAuth2::query()
            ->where('domain', $data['domain'])
            ->first();

        if (empty($record)) {
            $status = -1;
        } elseif (empty($record->oauth2_code)) {
            $status = 0;
        } else {
            $status = 1;
        }

        return ['status' => $status];
    }

    public function handleWebhookBotStatus(array $data): array
    {
        $record = UserChatThread::query()
            ->where('domain', $data['domain'])
            ->where('lead_id', $data['lead_id'])
            ->first();

        if (is_null($record)) {
            $record = UserChatThread::create([
                'domain' => $data['domain'],
                'lead_id' => $data['lead_id'],
                'status' => false,
            ]);
        }

        return ['status' => $record->status];
    }

    public function handleWebhookBotSwitch(array $data): array
    {
        $record = UserChatThread::query()
            ->where('domain', $data['domain'])
            ->where('lead_id', $data['lead_id'])
            ->first();

        if (is_null($record)) {
            $record = UserChatThread::create([
                'domain' => $data['domain'],
                'lead_id' => $data['lead_id'],
                'status' => false,
            ]);
        }

        $status = !$record->status;

        UserChatThread::query()
            ->where('domain', $data['domain'])
            ->where('lead_id', $data['lead_id'])
            ->update(['status' => $status]);

        return ['status' => $status];
    }

    public function handleWebhookPipelines(array $data): array
    {
        foreach ($data as $pipeline) {
            Pipeline::updateOrCreate(
                ['id' => $pipeline['id']],
                [
                    'account_id' => $pipeline['account_id'],
                    'name' => $pipeline['name'],
                    'sort' => $pipeline['sort'],
                    'is_main' => filter_var($pipeline['is_main'], FILTER_VALIDATE_BOOLEAN),
                    'is_unsorted_on' => filter_var($pipeline['is_unsorted_on'], FILTER_VALIDATE_BOOLEAN),
                    'is_archive' => filter_var($pipeline['is_archive'], FILTER_VALIDATE_BOOLEAN),
                ]
            );

            foreach ($pipeline['_embedded']['statuses'] as $status) {
                PipelineStatus::updateOrCreate(
                    ['id' => $status['id']],
                    [
                        'pipeline_id' => $status['pipeline_id'],
                        'name' => $status['name'],
                        'sort' => $status['sort'],
                        'type' => $status['type'],
                    ]
                );
            }
        }

        return ['status' => true];
    }

    public function handleWebhookLeadStatus(array $data, int $accountId): array
    {
        foreach ($data as $lead) {
            if (
                !is_numeric($lead['id']) ||
                !is_numeric($lead['pipeline_id']) ||
                !is_numeric($lead['status_id'])
            ) {
                Log::channel('amocrm')->error('Некорректные данные в лиде', ['lead' => $lead]);
                continue;
            }

            $leadId     = (int) $lead['id'];
            $statusId   = (int) $lead['status_id'];
            $pipelineId = (int) $lead['pipeline_id'];

            // --- сначала проверяем, есть ли pipeline ---
            if (!Pipeline::where('id', $pipelineId)->exists()) {
                Pipeline::create([
                    'id'             => $pipelineId,
                    'account_id'     => $accountId,
                    'name'           => 'Unknown',
                    'sort'           => 0,
                    'is_main'        => false,
                    'is_unsorted_on' => false,
                    'is_archive'     => false,
                ]);

                Log::channel('amocrm')->warning("Создан pipeline с Unknown", [
                    'pipeline_id' => $pipelineId,
                    'lead'        => $lead,
                ]);
            }

            // --- теперь можно создавать статус ---
            PipelineStatus::firstOrCreate(
                ['id' => $statusId],
                [
                    'pipeline_id' => $pipelineId,
                    'name'        => 'Unknown',
                    'sort'        => 0,
                    'type'        => 'regular',
                ]
            );

            // --- сохраняем связку лида с пайплайном/статусом ---
            LeadPipelineStatus::updateOrCreate(
                ['lead_id' => $leadId],
                [
                    'status_id'   => $statusId,
                    'pipeline_id' => $pipelineId,
                ]
            );
        }

        return ['status' => true];
    }


}
