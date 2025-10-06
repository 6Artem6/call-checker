<?php

namespace App\Models\AiLead\Chat;

use App\Services\OpenAI\{OpenAIAnalysisService, OpenAIArbitrationService};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use App\Models\AiLead\Account\AccountOAuth2;
use App\Models\AiLead\Chat\Abstracts\BaseChatMessage;

/**
 * @property integer $account_id
 * @property string $text
 * @property bool $has_reply
 * @property integer $reply_id
 *
 * @mixin Builder
 */
class ChatMessagePlayground extends BaseChatMessage
{
    protected $table = 'chat_message_playground';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'text',
        'account_id',
        'has_reply',
        'reply_id',
    ];
    protected $casts = [
        'id' => 'integer',
        'text' => 'string',
        'account_id' => 'integer',
        'has_reply' => 'boolean',
        'reply_id' => 'integer',
    ];

    private function analysis(string $content): array
    {
        $service = new OpenAIAnalysisService();
        $service->setSetting($this->setting);
        $service->setThreadByPlaygroundChat($this);

        return $service->chatFunction($content);
    }

    private function arbitration(array $messages, string $condition = null): bool
    {
        $service = new OpenAIArbitrationService();
        $service->setSetting($this->setting);

        $condition = $condition ?? $this->setting->completion_condition ?? 'Проверь, можно ли завершать диалог';

        [$verdict, $missing, $notes] = $service->arbitrateCondition($condition, $messages);

        Log::info('Arbitration result (playground)', [
            'verdict' => $verdict,
            'missing' => $missing,
            'notes'   => $notes,
        ]);

        return $verdict;
    }

    public function saveAnswerAnalysis(): void
    {
        Log::info(json_encode($this, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        if (!$this->has_reply) {
            $this->has_reply = true;
            $this->save();
        }

        $analysis_data = $this->text;
        Log::info("before analysis data");
        [$analysis_output, $status] = $this->analysis($analysis_data);

        if (empty($analysis_output) || !$status) {
            [$analysis_output, $status] = $this->analysis($analysis_data);
        }

        if (!empty($analysis_output) && $status) {
            $answer = self::create([
                'text' => preg_replace('/【.*?】/u', '', $analysis_output),
                'account_id' => $this->account_id,
                'has_reply' => null,
                'reply_id' => $this->id,
            ]);

            Log::info(json_encode($answer, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            // Арбитраж всего диалога
            $dialog = self::where('account_id', $this->account_id)
                ->orderBy('id')
                ->get()
                ->map(function ($msg) {
                    return [
                        'role' => $msg->reply_id ? 'bot' : 'user',
                        'content' => $msg->text,
                    ];
                })
                ->toArray();

            $verdict = $this->arbitration($dialog);

            if ($verdict) {
                PlaygroundChatThread::where('account_id', $this->account_id)
                    ->update(['status' => 0]);

                Log::info("Диалог закрыт арбитром", [
                    'account_id' => $this->account_id,
                ]);
            } else {
                $answer->sendAnswer();
            }
        } else {
            Log::warning("Analysis failed", [
                'message_id' => $this->id,
                'account_id' => $this->account_id,
            ]);
        }
    }

    public function sendAnswer()
    {
        $account = AccountOAuth2::where('account_id', $this->account_id)->first();
        if (!$account) {
            return response()->json(['error' => 'Account not found'], 404);
        }

        $data = $this->formatJsonToArray($this->text);
        $text = $this->formatArrayToText($data);

        // Тут можно интегрировать PuppeteerService, если нужно
        Log::info("Отправка ответа: {$text}");
    }
}
