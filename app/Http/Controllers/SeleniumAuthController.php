<?php

namespace App\Http\Controllers;

use App\Models\AccountOAuth2;
use App\Models\ChatMessage;
use App\Services\{SeleniumService, PuppeteerService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class SeleniumAuthController extends Controller
{
    protected $seleniumAuthService;
    protected $puppeteerService;

    public function __construct(SeleniumService $seleniumAuthService,
                                PuppeteerService $puppeteerService)
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '-1');

        $this->seleniumAuthService = $seleniumAuthService;
        $this->puppeteerService = $puppeteerService;
    }

    public function seleniumTestRequest($userId, Request $request)
    {
        $url = 'https://kirilltihiy.amocrm.ru';
        $username = 'kirill.tihiy@mail.ru';
        $password = '725513';
        $account_id = 32181490;
        $leadId = 32886545;
        $text = "test";

        $this->seleniumAuthService->init($account_id);
        $this->seleniumAuthService->setBaseUrl($url);
        $this->seleniumAuthService->setCredentials($username, $password);

        $status = $this->seleniumAuthService->loginWithToken($userId);

        $this->seleniumAuthService->sendLeadMessage($leadId, $text);

        return response()->json($status);
    }

    public function puppeteerTestRequest(Request $request)
    {
        $domain = 'kirilltihiy.amocrm.ru';
        $account_id = 32181490; // Идентификатор аккаунта
        $leadId = 32886545;
        $text = "test";

        $account = AccountOAuth2::where('domain', $domain)->first();

        if (!$account) {
            return response()->json(['error' => 'Account not found'], 404);
        }

        // Используем сессию из БД или account_id, если сессия не установлена
        if (!$account->session_id) {
            $sessionId = bin2hex(random_bytes(16)); // Генерируем случайный session_id
            $account->session_id = $sessionId;
            $account->save();
        } else {
            $sessionId = $account->session_id;
        }

        // Вызываем Puppeteer-сервис
        $this->puppeteerService->sendLeadMessageTest($account_id, $domain, $leadId, $text);

        $account->refresh();
        $sessionId = $account->session_id;

        // Логируем заголовки
        Log::info('Отправляем запрос с заголовками:', [
            'Cookie' => "X-Session-ID={$sessionId}; Path=/; HttpOnly",
        ]);

        // Устанавливаем куку X-Session-ID
        return response()->json(['status' => 'ok'])
            ->cookie('X-Session-ID', $sessionId, 0, '/', parse_url(env('PUPPETEER_API_URL'), PHP_URL_HOST), false, true);
    }

}
