<?php

namespace App\Http\Controllers;

use App\Jobs\SendAnswer;
use App\Models\AiLead\Account\AccountOAuth2;
use App\Models\AiLead\Chat\ChatMessagePlayground;
use App\Models\AiLead\Gpt\ChatGPTFile;
use App\Models\AiLead\Gpt\ChatGPTSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PlaygroundController extends Controller
{
    public function chatForm(Request $request, int $account_id = 0): Response
    {
        /** @var AccountOAuth2|null $account */
        $account = Auth::guard('api')->user()?->oauth2;
        if (!$account || ($account_id && $account->account_id != $account_id)) {
            abort(404);
        }
        if (!$account_id) {
            $account_id = $account->account_id;
        }
        $settings = $account->gptSettings()->get();

        $activePipelineId = $request->input('pipeline_status_id')
            ?: ($settings->first()?->pivot->pipeline_status_id ?? null);

        $activeSetting = null;
        if ($activePipelineId) {
            $activeSetting = $settings
                ->first(fn($s) => $s->pivot->pipeline_status_id == $activePipelineId)
                ?->replicate();
        }

        return Inertia::render('Playground/Form', [
            'account_id'          => $account_id,
            'settings'            => $settings,
            'active_pipeline_id'  => $activePipelineId,
            'setting'             => $activeSetting,
        ]);
    }

    public function sendMessage(Request $request, int $account_id = 0): JsonResponse
    {
        /** @var AccountOAuth2|null $account */
        $account = Auth::guard('api')->user()?->oauth2;
        if (!$account || ($account_id && $account->account_id != $account_id)) {
            abort(404);
        }
        if (!$account_id) {
            $account_id = $account->account_id;
        }

        $payload = $request->only(['text', 'setting_id']);
        Log::channel('amocrm')->info('PlaygroundController@sendMessage payload: ' . json_encode($payload, JSON_UNESCAPED_UNICODE));

        $message = ChatMessagePlayground::create([
            'text'       => (string)$payload['text'],
            'account_id' => $account_id,
            'domain'     => $account->domain,
            'has_reply'  => false,
            'status'     => 'pending',
        ]);

        $setting = ChatGPTSetting::find($payload['setting_id']);
        if ($setting) {
            $message->setSetting($setting);
            SendAnswer::dispatch($message->id, $setting->setting_id);
            Log::channel('amocrm')->info("Dispatched SendAnswer job for message {$message->id}");
        } else {
            Log::channel('amocrm')->warning("ChatGPTSetting with id {$payload['setting_id']} not found.");
        }

        return response()->json([
            'status' => 'ok',
            'id' => $message->id,
        ]);
    }

    public function checkMessageStatus(Request $request, int $message_id): JsonResponse
    {
        $message = ChatMessagePlayground::find($message_id);
        if (!$message) {
            return response()->json(['error' => 'Message not found'], 404);
        }

        $reply = ChatMessagePlayground::query()->where('reply_id', '=', $message_id)->first();
        if (!$reply) {
            return response()->json(['status' => 'pending']);
        }
        
        $status = $message->status;

        $response = ['status' => $status];

        if ($status === 'completed') {
            $data = $reply->formatJsonToArray($reply->text);
            $text = $reply->formatArrayToText($data);
            $noteText = ""; // $reply->formatArrayDataToText($data);
            $response['result'] = $text . $noteText;
        } elseif (in_array($status, ['failed', 'cancelled', 'expired'])) {
            $response['error'] = 'Генерация сообщения прекратилась или закончилась ошибкой.';
        }

        return response()->json($response);
    }

    public function accountMessages(Request $request, int $account_id): JsonResponse
    {
        $messages = ChatMessagePlayground::query()
            ->where('account_id', $account_id)
            ->orderBy('id')
            ->get();

        // Группировка по reply_id
        $grouped = $messages->groupBy('reply_id');

        // Построим дерево: parent -> [children...]
        $result = [];

        foreach ($grouped[null] ?? [] as $parent) {
            $result[] = [
                'id' => $parent->id,
                'text' => $parent->text,
                'status' => $parent->status,
                'has_reply' => $parent->has_reply,
                'replies' => collect($grouped[$parent->id] ?? [])->map(function ($reply) {
                    $data = $reply->formatJsonToArray($reply->text);
                    $text = $reply->formatArrayToText($data);
                    $noteText = ""; // $reply->formatArrayDataToText($data);
                    $taskText = ""; // $reply->formatTasksToText($data);
                    return [
                        'id' => $reply->id,
                        'text' => trim($text . $noteText . $taskText),
                        'status' => $reply->status,
                        'has_reply' => $reply->has_reply,
                    ];
                })->values(),
            ];
        }

        return response()->json($result);
    }
}
