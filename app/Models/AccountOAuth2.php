<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AccountOAuth2 extends Model
{
    protected $table = 'account_oauth2';
    protected $primaryKey = 'account_id';
    protected $fillable = [
        'account_id',
        'domain',
        'oauth2_code',
        'access_token',
        'refresh_token',
        'expires_in',
    ];
    public $timestamps = false;

    protected $casts = [
        'domain' => 'string',
        'oauth2_code' => 'string',
        'access_token' => 'string',
        'refresh_token' => 'string',
        'expires_in' => 'datetime',
    ];

    public static function retrieveAccessData(?string $domain, ?string $oauth2_code): string
    {
        $domain = strtolower($domain);
        $domain = str_replace(['http://', 'https://'], '', $domain);
        $model = AccountOAuth2::where(['domain' => $domain])->first();
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
                $time = strtotime('+' . $response->json('expires_in') . ' seconds');
                $model->update([
                    "access_token" => $response->json('access_token'),
                    "refresh_token" => $response->json('refresh_token'),
                    "expires_in" => date('Y-m-d H:i:s', $time),
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

    public function refreshAccessData()
    {
        $response = Http::baseUrl('https://' . $this->domain)
            ->withHeader('Content-Type', 'application/json')
            ->post('/oauth2/access_token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->refresh_token,
                'client_id' => env('AMOCRM_CLIENT_ID'),
                'client_secret' => env('AMOCRM_CLIENT_SECRET'),
                'redirect_uri' => env('AMOCRM_REDIRECT_URI'),
            ]);
        if ($response->successful()) {
            $time = strtotime('+' . $response->json('expires_in') . ' seconds');
            $this->update([
                "access_token" => $response->json('access_token'),
                "refresh_token" => $response->json('refresh_token'),
                "expires_in" => date('Y-m-d H:i:s', $time),
            ]);
            $this->refresh();
        } else {
            Log::error($response->body());
        }
    }

    public function getSubdomain() {
        return explode('.', $this->domain)[0] ?? null;
    }

    public function isTokenExpired() {
        return (time() >= strtotime($this->expires_in));
    }
}
