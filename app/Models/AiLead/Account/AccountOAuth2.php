<?php

namespace App\Models\AiLead\Account;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;
use App\Models\AiLead\Gpt\ChatGPTSetting;
use App\Models\AiLead\Pipeline\Pipeline;
use App\Models\AiLead\Pipeline\PipelineStatus;


class AccountOAuth2 extends Authenticatable
{
    use HasApiTokens;
    
    protected $table = 'account_oauth2';
    protected $primaryKey = 'account_id';
    protected $fillable = [
        'account_id',
        'domain',
        'is_active',
        'oauth2_code',
        'access_token',
        'refresh_token',
        'session_id',
        'expires_in',
        'needs_reconnect',
    ];
    protected $hidden = [
        'user_id'
    ];
    public $timestamps = false;

    protected $casts = [
        'user_id' => 'integer',
        'account_id' => 'integer',
        'domain' => 'string',
        'is_active' => 'boolean',
        'oauth2_code' => 'string',
        'access_token' => 'string',
        'refresh_token' => 'string',
        'session_id' => 'string',
        'expires_in' => 'datetime',
        'needs_reconnect' => 'boolean',
    ];

    public static function retrieveAccessData(?string $domain, ?string $oauth2_code): string
    {
        $domain = strtolower($domain);
        $domain = str_replace(['http://', 'https://'], '', $domain);
        $model = self::where(['domain' => $domain])->first();
        if (!$model) {
            $model = new self;
            $model->domain = $domain;
            $model->save();
        }
        if ($model->oauth2_code !== $oauth2_code) {
            $model->update(['oauth2_code' => $oauth2_code]);
            $response = Http::baseUrl('https://' . $domain)
                ->withHeader('Content-Type', 'application/json')
                ->post('/oauth2/access_token', [
                    'grant_type' => 'authorization_code',
                    'code' => $oauth2_code,
                    'client_id' => env('AMOCRM_CLIENT_ID'),
                    'client_secret' => env('AMOCRM_CLIENT_SECRET'),
                    'redirect_uri' => env('AMOCRM_REDIRECT_URI'),
                ]);
            if ($response->successful()) {
                $model->update([
                    "access_token" => $response->json('access_token'),
                    "refresh_token" => $response->json('refresh_token'),
                    'expires_in'    => now()->addSeconds($response->json('expires_in')),
                ]);
                $message = "Плагин был успешно подключен.";
            } else {
                $message = "Не удалось подключить плагин.";
            }
        } else {
            $message = "Плагин уже был успешно подключен.";
        }
        return $message;
    }

    public function refreshAccessData(): void
    {
        DB::transaction(function () {
            // блокируем строку аккаунта для избежания гонок
            $account = AccountOAuth2::where('account_id', $this->account_id)->lockForUpdate()->first();

            $account->update([
                'needs_reconnect' => false, // флаг в БД — аккаунт требует ручной реавторизации
            ]);

            // если access_token ещё живой — не обновляем
//            if ($account->expires_in && now()->lt($account->expires_in)) {
//                return;
//            }

            $response = Http::baseUrl('https://' . $account->domain)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post('/oauth2/access_token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $account->refresh_token,
                    'client_id' => env('AMOCRM_CLIENT_ID'),
                    'client_secret' => env('AMOCRM_CLIENT_SECRET'),
                    'redirect_uri' => env('AMOCRM_REDIRECT_URI'),
                ]);
            Log::info('Body: ' . json_encode($response->json(), JSON_UNESCAPED_UNICODE));

            if ($response->successful()) {
                $account->update([
                    "access_token" => $response->json('access_token'),
                    "refresh_token" => $response->json('refresh_token'),
                    'expires_in'    => now()->addSeconds($response->json('expires_in')),
                ]);
            } else {
                $body = $response->json();

                // refresh протух/отозван
                if (($body['status'] ?? null) === 401 || str_contains($body['detail'] ?? '', 'revoked')) {
                    $account->update([
                        'needs_reconnect' => true, // флаг в БД — аккаунт требует ручной реавторизации
                    ]);
                }

                Log::error('Token refresh failed: ' . json_encode($body, JSON_UNESCAPED_UNICODE));
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(AccountUser::class, 'user_id', 'user_id');
    }

    public function gptSettings()
    {
        return $this->belongsToMany(
            ChatGPTSetting::class,
            'account_gpt_settings',
            'account_id',
            'setting_id'
        )->withPivot('pipeline_status_id');
    }

    public function pipelineStatuses()
    {
        return $this->hasManyThrough(
            PipelineStatus::class,
            Pipeline::class,
            'account_id',   // foreignKey в таблице pipelines
            'pipeline_id',  // foreignKey в таблице pipeline_statuses
            'account_id',   // localKey в AccountOAuth2
            'id'            // localKey в Pipeline
        );
    }

    public function getSubdomain() {
        return explode('.', $this->domain)[0] ?? null;
    }

    public function isTokenExpired() {
        return (time() >= strtotime($this->expires_in));
    }
}
