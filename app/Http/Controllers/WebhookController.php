<?php

namespace App\Http\Controllers;

use App\Models\AiLead\Chat\{ChatMessage};
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;


class WebhookController extends Controller
{

    public function __construct(
        private readonly WebhookService $webhookService
    )
    {
        ini_set('max_execution_time', 600);
        ini_set('memory_limit' , '-1');
    }

    /**
     * Обработка входящего вебхука от чата amoCRM
     */
    public function handleWebhook(Request $request): void
    {
        // Быстрый ответ AmoCRM
        response()->json(['status' => 'success'])->send();

        Log::channel('amocrm')->info('AmoCRM Webhook Received:' .
            json_encode($request->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $validator = Validator::make($request->all(), [
            'account._links.self' => ['required', 'string'],
            'message.add.0.text' => ['required', 'string'],
            'message.add.0.contact_id' => ['required', 'string'],
            'message.add.0.chat_id' => ['required', 'string'],
            'message.add.0.origin' => ['required', 'string'],
            'message.add.0.entity_type' => ['required', 'string'],
            'message.add.0.entity_id' => ['required', 'string'],
        ]);

        if ($validator->passes()) {
            $this->webhookService->handleMessageWebhook($validator->validated());
        } else {
            Log::channel('amocrm')->warning('Validation failed', $validator->errors()->toArray());
        }
    }

    public function handleWebhookPluginActivate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer'],
            'domain' => ['required', 'string'],
        ]);

        $result = $this->webhookService->handleWebhookPluginActivate($validated);
        return response()->json($result);
    }

    public function handleWebhookPluginStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string'],
        ]);
        $result = $this->webhookService->handleWebhookPluginStatus($validated);
        return response()->json($result);
    }

    public function handleWebhookBotStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string'],
            'lead_id' => ['required', 'integer'],
        ]);

        $result = $this->webhookService->handleWebhookBotStatus($validated);
        return response()->json($result);
    }

    public function handleWebhookBotSwitch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string'],
            'lead_id' => ['required', 'integer'],
        ]);

        $result = $this->webhookService->handleWebhookBotSwitch($validated);
        return response()->json($result);
    }

    public function handleWebhookPipelines(Request $request): JsonResponse
    {
        $data = $request->input('pipelines');

        $validator = Validator::make(['pipelines' => $data], [
            'pipelines' => ['required', 'array'],
            'pipelines.*.id' => ['required', 'integer'],
            'pipelines.*.account_id' => ['required', 'integer'],
            'pipelines.*.name' => ['required', 'string'],
            'pipelines.*.sort' => ['required', 'integer'],
            'pipelines.*.is_main' => ['required', 'boolean'],
            'pipelines.*.is_unsorted_on' => ['required', 'boolean'],
            'pipelines.*.is_archive' => ['required', 'boolean'],
            'pipelines.*._embedded.statuses' => ['required', 'array'],
            'pipelines.*._embedded.statuses.*.id' => ['required', 'integer'],
            'pipelines.*._embedded.statuses.*.pipeline_id' => ['required', 'integer'],
            'pipelines.*._embedded.statuses.*.name' => ['required', 'string'],
            'pipelines.*._embedded.statuses.*.sort' => ['required', 'integer'],
            'pipelines.*._embedded.statuses.*.type' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            Log::channel('amocrm')->warning('Validation failed', $validator->errors()->toArray());
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->webhookService->handleWebhookPipelines($validator->validated()['pipelines']);
        return response()->json($result);
    }

    public function handleWebhookLeadAdd(Request $request): JsonResponse
    {
        Log::channel('amocrm')->info('AmoCRM Webhook LeadAdd:', $request->toArray());

        $accountId = $request->input('account.id');

        $leads = $request->input('leads.add', []);

        $validator = Validator::make(['leads' => $leads], [
            'leads' => ['required', 'array'],
            'leads.*.id' => ['required', 'numeric'],
            'leads.*.pipeline_id' => ['required', 'numeric'],
            'leads.*.status_id' => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            Log::channel('amocrm')->error('Validation failed', ['errors' => $validator->errors()->toArray()]);
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->webhookService->handleWebhookLeadStatus($validator->validated()['leads'], $accountId);
        return response()->json($result);
    }

    public function handleWebhookLeadStatus(Request $request): JsonResponse
    {
        Log::channel('amocrm')->info('AmoCRM Webhook LeadStatus:', $request->toArray());

        $accountId = $request->input('account.id');

        $leads = $request->input('leads.update', []);
        if (empty($leads)) {
            $leads = $request->input('leads.add', []);
        }

        $validator = Validator::make(['leads' => $leads], [
            'leads' => ['required', 'array'],
            'leads.*.id' => ['required', 'numeric'],
            'leads.*.pipeline_id' => ['required', 'numeric'],
            'leads.*.status_id' => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            Log::channel('amocrm')->error('Validation failed', ['errors' => $validator->errors()->toArray()]);
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->webhookService->handleWebhookLeadStatus($validator->validated()['leads'], $accountId);
        return response()->json($result);
    }


    /**
     * Обработка входящего вебхука от канала amoCRM
     */
    public function handleWebhookChannel(Request $request): JsonResponse
    {
        // Логирование всех входящих данных для отладки
        Log::channel('amocrm')->info('AmoCRM Webhook Channel Received:' .
            json_encode($request->all(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        Log::channel('amocrm')->info('before validated');

        // Ответ AmoCRM (обязательно для подтверждения получения вебхука)
        return response()->json(['status' => 'success', 'scope_id' => $request->scope_id]);
    }

    public function handleWebhookWuzzap(Request $request): JsonResponse
    {
        // Логирование всех входящих данных для отладки
        Log::channel('amocrm')->info('AmoCRM Webhook Wuzzap Received:' .
            json_encode($request->all(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        Log::channel('amocrm')->info('before validated');

        // Валидация входящих данных (пример)
        $validated = $request->validate([
            'messages' => ['required', 'array'],
            'messages.0.text' => ['required', 'string'],
            'messages.0.channelId' => ['required', 'string'],
            'messages.0.chatId' => ['required', 'string'],
            'messages.0.chatType' => ['required', 'string'],
            'messages.0.status' => ['required', 'string'],
        ]);
        Log::channel('amocrm')->info('validated');

        $data = $validated['messages'][0];
        if ($data['status'] === 'inbound') {
            // Обработка данных (пример: запись в базу данных)
            $message = new ChatMessage();
            $message->text = $data['text'];
            $message->contact_id = $data['chatId'];
            $message->chat_id = $data['channelId'];
            $message->origin = $data['chatType'];
            $message->has_reply = false;
            $message->reply_id = null;
            $message->save();

            Log::channel('amocrm')->info('save');
            if ($message->origin === 'telegram') {
                $message->saveAnswerAnalysis();
            }
        }
        // Ответ AmoCRM (обязательно для подтверждения получения вебхука)
        return response()->json(['status' => 'success']);
    }

    public function handleWebhookI2crm(Request $request): JsonResponse
    {
        // Логирование всех входящих данных для отладки
        Log::channel('amocrm')->info('AmoCRM Webhook I2crm Received:' .
            json_encode($request->all(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        Log::channel('amocrm')->info('before validated');

        // Ответ AmoCRM (обязательно для подтверждения получения вебхука)
        return response()->json(['status' => 'success']);
    }
}
