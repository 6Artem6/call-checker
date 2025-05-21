<?php

namespace App\Models;

use App\Services\OpenAIAnalysisService;
use App\Services\SeleniumService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * @property integer $contact_id
 * @property integer $chat_id
 * @property integer $lead_id
 * @property string $domain
 * @property string $text
 * @property string $origin
 * @property string $has_reply
 * @property string $reply_id
 *
 * @mixin Builder
 */
class ChatMessage extends Model
{
    protected $table = 'chat_message';
    protected $primaryKey = 'id';
    protected $fillable = [
        'contact_id',
        'chat_id',
        'lead_id',
        'text',
        'domain',
        'origin',
        'has_reply',
        'reply_id',
    ];
    protected $hidden = [
        'id'
    ];

    protected ChatGPTSetting $setting;

    public static function rules(): array
    {
        return [
            'id' => ['unique:chat_messages,id'],
            'contact_id' => ['required', 'string', 'max:50'],
            'chat_id' => ['required', 'string', 'max:50'],
            'lead_id' => ['required', 'integer'],
            'domain' => ['required', 'string', 'max:255'],
            'text' => ['required', 'string', 'max:25'],
            'origin' => ['required', 'string', 'max:25'],
            'has_reply' => ['required', 'boolean'],
            'reply_id' => ['required', 'integer'],
        ];
    }

    public function setSetting(ChatGPTSetting $setting) {
        $this->setting = $setting;
    }

    public function saveAnswerAnalysis(): void
    {
        Log::info(json_encode($this, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        if (!$this->has_reply) {
            $this->has_reply = true;
            $this->save();
        }

        $analysis_data = [
            $this->text
        ];
        $analysis_content = json_encode($analysis_data, JSON_UNESCAPED_UNICODE);
        Log::info("before analysis data");
        [$analysis_output, $status] = $this->analysis($analysis_content);
        Log::info(json_encode($analysis_output, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        if (!empty($analysis_output) && $status) {
            /** @var self $answer */
            $answer = $this->create([
                'contact_id' => $this->contact_id,
                'chat_id' => $this->chat_id,
                'text' => $analysis_output,
                'domain' => $this->domain,
                'lead_id' => $this->lead_id,
                'has_reply' => null,
                'reply_id' => $this->id,
                'origin' => $this->origin,
            ]);

            Log::info(json_encode($answer, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));

            $answer->sendAnswer();
            Log::info("after http");
        }
    }

    private function analysis(string $content): array
    {
        $service = new OpenAIAnalysisService;
        $service->setThreadByUserChat($this);
        $service->setSetting($this->setting);

        return $service->chatFunction($content);
    }

    private function sendAnswer()
    {
        $url = '';
        $username = '';
        $password = '';
        $userId = 0;

        $seleniumAuthService = new SeleniumService;
        $seleniumAuthService->baseUrl = $url;
        $seleniumAuthService->username = $username;
        $seleniumAuthService->password = $password;

        $status = $seleniumAuthService->loginWithToken($userId);

        $seleniumAuthService->sendLeadMessage($this->lead_id, $this->text);
    }

    private function sendAnswerSeleniumCookies()
    {
        $url = '';
        $username = '';
        $password = '';
        $userId = 0;

        $seleniumAuthService = new SeleniumService;
        $seleniumAuthService->baseUrl = $url;
        $seleniumAuthService->username = $username;
        $seleniumAuthService->password = $password;

        $status = $seleniumAuthService->loginWithCookies($userId);

        $seleniumAuthService->sendLeadMessage($this->lead_id, $this->text);
    }

    private function sendAnswerWazzup()
    {
        $is_telegram = $this->origin === "telegram";
        Http::withHeaders([
            'Authorization' => 'Bearer ' . env('WUZZAP_AUTHORIZATION_BEARER'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
            ->baseUrl(env('WUZZAP_API_URL'))
            ->post('/message', [
                "channelId" => $is_telegram ?
                    env('WUZZAP_TELEGRAM_CHANNEL_ID') : env('WUZZAP_WHATSAPP_CHANNEL_ID'),
                "chatType" => $this->origin,
                "chatId" => ($is_telegram ? '' : '+') . $this->contact_id,
                "text" => $this->text
            ]);
        Log::info("after http");
    }
}
