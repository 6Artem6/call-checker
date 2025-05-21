<?php

namespace App\Http\Controllers;

use App\Jobs\SendAnswer;
use App\Models\AccountOAuth2;
use App\Models\ChatGPTSetting;
use App\Models\ChatMessage;
use App\Models\UserChatThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;


class WebhookController extends Controller
{

    public function __construct()
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

        // Фоновая логика
        Log::info('AmoCRM Webhook Received:' .
            json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        Log::info('before validated');

        try {
            $validator = Validator::make($request->all(), [
                'account._links.self' => ['required', 'string'],
                'message.add.0.text' => ['required', 'string'],
                'message.add.0.contact_id' => ['required', 'string'],
                'message.add.0.chat_id' => ['required', 'string'],
                'message.add.0.origin' => ['required', 'string'],
                'message.add.0.entity_type' => ['required', 'string'],
                'message.add.0.entity_id' => ['required', 'string'],
            ]);

            if ($validator->fails()) {
                Log::warning('Validation failed', $validator->errors()->toArray());
                return; // Ответ уже отправлен, просто выходим
            }

            $validated = $validator->validated();
            Log::info('validated');

            $data = $validated['message']['add'][0];

            if ($data['entity_type'] === 'lead') {
                $data['domain'] = parse_url($validated['account']['_links']['self'])['host'];
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
                Log::info('saved');

                Log::info(json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                if ($record->status) {
                    $setting = ChatGPTSetting::query()
                        ->with('account')
                        ->whereHas('account', function ($query) use ($data) {
                            $query->where('domain', $data['domain']);
                        })
                        ->first();

                    SendAnswer::dispatch($message, $setting);
                    Log::info('after dispatch');
                }
            }
        } catch (\Throwable $e) {
            Log::error('Ошибка в обработке вебхука: ' . $e->getMessage());
        }
    }

    /**
     * Обработка входящего вебхука от канала amoCRM
     */
    public function handleWebhookChannel(Request $request): JsonResponse
    {
        // Логирование всех входящих данных для отладки
        Log::info('AmoCRM Webhook Channel Received:' .
            json_encode($request->all(), JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        Log::info('before validated');

        // Ответ AmoCRM (обязательно для подтверждения получения вебхука)
        return response()->json(['status' => 'success', 'scope_id' => $request->scope_id]);
    }

    public function handleWebhookWuzzap(Request $request): JsonResponse
    {
        // Логирование всех входящих данных для отладки
        Log::info('AmoCRM Webhook Wuzzap Received:' .
            json_encode($request->all(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        Log::info('before validated');

        // Валидация входящих данных (пример)
        $validated = $request->validate([
            'messages' => ['required', 'array'],
            'messages.0.text' => ['required', 'string'],
            'messages.0.channelId' => ['required', 'string'],
            'messages.0.chatId' => ['required', 'string'],
            'messages.0.chatType' => ['required', 'string'],
            'messages.0.status' => ['required', 'string'],
        ]);
        Log::info('validated');

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

            Log::info('save');
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
        Log::info('AmoCRM Webhook I2crm Received:' .
            json_encode($request->all(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        Log::info('before validated');

        // Ответ AmoCRM (обязательно для подтверждения получения вебхука)
        return response()->json(['status' => 'success']);
    }

    public function handleWebhookPluginActivate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer'],
            'domain' => ['required', 'string'],
        ]);

        $record = AccountOAuth2::updateOrCreate(
            ['domain' => $validated['domain']],
            ['account_id' => $validated['account_id']]
        );
        $settings = ChatGPTSetting::createOrFirst(
            ['account_id' => $validated['account_id']]
        );
        return response()->json(['status' => (!is_null($record) && !is_null($settings))]);
    }

    public function handleWebhookPluginStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string'],
        ]);

        $record = AccountOAuth2::query()
            ->where('domain', $validated['domain'])
            ->first();
        if (empty($record)) {
            $status = -1;
        } elseif (empty($record->oauth2_code)) {
            $status = 0;
        } else {
            $status = 1;
        }
        return response()->json(['status' => $status]);
    }

    public function handleWebhookBotStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string'],
            'lead_id'   => ['required', 'integer'],
        ]);

        $record = UserChatThread::query()
            ->where('domain', $validated['domain'])
            ->where('lead_id', $validated['lead_id'])
            ->first();
        if (is_null($record)) {
            $record = UserChatThread::create([
                'domain' => $validated['domain'],
                'lead_id' => $validated['lead_id'],
                'status' => true,
            ]);
        }
        return response()->json(['status' => $record->status]);
    }

    public function handleWebhookBotSwitch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string'],
            'lead_id'   => ['required', 'integer'],
        ]);

        $record = UserChatThread::query()
            ->where('domain', $validated['domain'])
            ->where('lead_id', $validated['lead_id'])
            ->first();
        if (is_null($record)) {
            $record = UserChatThread::create([
                'domain' => $validated['domain'],
                'lead_id' => $validated['lead_id'],
                'status' => true,
            ]);
        }

        $status = !$record->status;
        UserChatThread::query()
            ->where('domain', $validated['domain'])
            ->where('lead_id', $validated['lead_id'])
            ->update(['status' => $status]);

        return response()->json(['status' => $status]);
    }
}
