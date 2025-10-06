<?php

namespace App\Services;

use App\Models\AiLead\Account\AccountOAuth2;
use App\Models\AiLead\Account\UserCookie;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Client;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\ConnectionException;
use Exception;


class PuppeteerService
{

    private function encryptData($data)
    {
        $key = base64_decode(env('ENCRYPTION_KEY'));
        $key = substr(hash('sha256', $key, true), 0, 32); // Приводим ключ к 32 байтам

        $iv = random_bytes(16);
        $encrypted = openssl_encrypt(json_encode($data), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return base64_encode($iv . $encrypted); // IV + данные в base64
    }

    /**
     * Получает cookies пользователя
     */
    private function getCookies($userId)
    {
        return UserCookie::where('user_id', $userId)->get();
    }

    /**
     * Отправка сообщения в лид через Puppeteer API
     * @throws Exception
     */
    public function sendLeadMessage(int $account_id, string $domain, int $leadId, string $messageText, string $noteText = "", string $taskText = "")
    {
        $baseUrl = "https://" . $domain;
        $account = AccountOAuth2::query()->where('domain', $domain)->first();
        if (!$account) {
            throw new Exception("Account not found");
        }

        if ($account->isTokenExpired()) {
            Log::warning("token expired");
            $account->refreshAccessData();
        }

        // Получаем куки для пользователя и подставляем актуальные токены
        $cookies = $this->getCookies($account_id)->map(function ($cookie) use ($account) {
            if ($cookie->name == 'last_login') {
                $cookie->value = '';
            } elseif ($cookie->name == 'access_token') {
                $cookie->value = $account->access_token;
            } elseif ($cookie->name == 'refresh_token') {
                $cookie->value = $account->refresh_token;
            }
            return $cookie;
        })->toArray();

        // Собираем базовый payload
        $basePayload = [
            'account_id'    => $account_id,
            'base_url'      => $baseUrl,
            'access_token'  => $account->access_token,
            'refresh_token' => $account->refresh_token,
            'lead_id'       => $leadId,
            'message_text'  => $messageText,
            'note_text'     => $noteText,
            'task_text'     => $taskText,
            'expiry'        => strtotime($account->expires_in)
        ];

        $maxAttempts = 5;
        $attempt = 0;
        $anyFulfilled = false;
        $lastErrorMessage = '';

        while ($attempt < $maxAttempts && !$anyFulfilled) {
            // Каждый раз инициализируем массив промисов заново
            $promises = [];

            // Если требуется, модифицируем payload (это просто копия basePayload)
            $modifiedPayload = $basePayload;

            // Шифруем данные
            $encryptedPayload = $this->encryptData($modifiedPayload);

            // Отправляем запрос с явной передачей заголовка и куки X-Session-ID, а также заголовка Host
            $promises[] = Http::timeout(300)
                ->withHeaders([
                    'Cookie' => "X-Session-ID={$account->session_id}",
                ])
                ->async()
                ->baseUrl(env('PUPPETEER_API_URL'))
                ->post("/send-lead-message", [
                    'data' => $encryptedPayload
                ]);

            // Ждём выполнения всех асинхронных запросов
            $results = Utils::settle($promises)->wait();

            foreach ($results as $result) {
                if ($result['state'] === 'fulfilled') {
                    $response = $result['value'];
                    // Если $response является исключением ConnectionException, получаем сообщение через getMessage, иначе используем тело ответа
                    $message = ($response instanceof ConnectionException)
                        ? $response->getMessage()
                        : $response->body();
                    Log::channel('amocrm')->info("Запрос выполнен", ['response' => $message]);

                    // Если сервер вернул куки через Set-Cookie, пробуем извлечь X-Session-ID
                    if (method_exists($response, 'headers') && isset($response->headers()['Set-Cookie'])) {
                        foreach ($response->headers()['Set-Cookie'] as $cookie) {
                            // Извлекаем значение куки до первого ';'
                            if (preg_match('/X-Session-ID=([^;]+)/', $cookie, $matches)) {
                                $newSessionId = $matches[1];
                                if ($account->session_id !== $newSessionId) {
                                    // Обновляем модель и сохраняем в БД
                                    $account->session_id = $newSessionId;
                                    $account->save();
                                    Log::channel('amocrm')->info("Обновлённая X-Session-ID сохранена в БД: {$newSessionId}");
                                }
                            }
                        }
                    }
                    $anyFulfilled = true;
                    break; // Прерываем цикл, если хотя бы один запрос успешен
                } else {
                    // Обработка ошибок
                    $error = $result['reason'];
                    if ($error instanceof RequestException && method_exists($error, 'response') && $error->response) {
                        $statusCode = $error->response->status();
                        $errorMessage = $error->response->body();
                    } elseif ($error instanceof ConnectionException) {
                        $statusCode = null;
                        $errorMessage = $error->getMessage();
                    } else {
                        $statusCode = null;
                        $errorMessage = $error->getMessage();
                    }

                    Log::error("Ошибка запроса (попытка " . ($attempt + 1) . ")", [
                        'status' => $statusCode,
                        'error'  => $errorMessage
                    ]);

                    $lastErrorMessage = $errorMessage;
                }
            }

            // Если ни один запрос не был успешным, увеличиваем счетчик попыток и делаем экспоненциальную задержку
            if (!$anyFulfilled) {
                $attempt++;
                // Экспоненциальная задержка: 100 мс, 200 мс, 400 мс, и т.д.
                usleep(100000 * (2 ** $attempt));
            }
        }

        if (!$anyFulfilled) {
            throw new Exception("Превышено максимальное количество попыток отправки запроса. Последняя ошибка: " . $lastErrorMessage);
        }
    }

    /**
     * Получение истории сообщений сделки
     */
    public function getLeadHistory(int $account_id, string $domain, int $leadId, ?int $stopAt = null, int $limit = 100): array
    {
        $baseUrl = "https://" . $domain;
        $account = AccountOAuth2::query()->where('domain', $domain)->first();
        if (!$account) {
            throw new Exception("Account not found");
        }

        if ($account->isTokenExpired()) {
            Log::warning("token expired");
            $account->refreshAccessData();
        }

        // куки (если нужны Puppeteer'у)
        $cookies = $this->getCookies($account_id)->map(function ($cookie) use ($account) {
            if ($cookie->name == 'last_login') {
                $cookie->value = '';
            } elseif ($cookie->name == 'access_token') {
                $cookie->value = $account->access_token;
            } elseif ($cookie->name == 'refresh_token') {
                $cookie->value = $account->refresh_token;
            }
            return $cookie;
        })->toArray();

        $payload = [
            'account_id'    => $account_id,
            'base_url'      => $baseUrl,
            'access_token'  => $account->access_token,
            'refresh_token' => $account->refresh_token,
            'lead_id'       => $leadId,
            'stop_at'       => $stopAt, // timestamp — до какого created_at грузить
            'limit'         => $limit,
            'expiry'        => strtotime($account->expires_in),
        ];

        $encryptedPayload = $this->encryptData($payload);

        $response = Http::timeout(300)
            ->withHeaders([
                'Cookie' => "X-Session-ID={$account->session_id}",
            ])
            ->baseUrl(env('PUPPETEER_API_URL'))
            ->post("/get-lead-history", [
                'data' => $encryptedPayload,
            ]);

        if ($response->failed()) {
            Log::channel('amocrm')->error("Ошибка при получении истории amoCRM", [
                'lead_id' => $leadId,
                'error'   => $response->body(),
            ]);
            throw new Exception("История не получена: " . $response->body());
        }

        return $response->json();
    }

    public function sendLeadMessageTest(int $account_id, string $domain, int $leadId, string $messageText)
    {
        $baseUrl = "https://" . $domain;
        $account = AccountOAuth2::query()->where('domain', $domain)->first();

        if (!$account) {
            throw new Exception("Account not found");
        }

        if ($account->isTokenExpired()) {
            $account->refreshAccessData();
        }

        // Получаем куки для пользователя и подставляем актуальные токены
        $cookies = $this->getCookies($account_id)->map(function ($cookie) use ($account) {
            if ($cookie->name == 'last_login') {
                $cookie->value = '';
            } elseif ($cookie->name == 'access_token') {
                $cookie->value = $account->access_token;
            } elseif ($cookie->name == 'refresh_token') {
                $cookie->value = $account->refresh_token;
            }
            return $cookie;
        })->toArray();

        // Собираем базовый payload
        $basePayload = [
            'account_id'    => $account_id,
            'base_url'      => $baseUrl,
            'access_token'  => $account->access_token,
            'refresh_token' => $account->refresh_token,
            'lead_id'       => $leadId,
            'message_text'  => $messageText,
            'expiry'        => strtotime($account->expires_in)
        ];

        $maxAttempts = 5;
        $attempt = 0;
        $anyFulfilled = false;
        $lastErrorMessage = '';

        while ($attempt < $maxAttempts && !$anyFulfilled) {
            $promises = [];

            for ($i = 0; $i < 10; $i++) {
                // Модифицируем account_id случайным образом (если нужно)
                // $randomAdjustment = rand(0, 50);
                $modifiedPayload = $basePayload;
                // $modifiedPayload['account_id'] += $randomAdjustment;

                // Шифруем данные
                $encryptedPayload = $this->encryptData($modifiedPayload);

                $promises[] = Http::withHeaders([
                    'X-Session-ID' => /*$account->session_id,*/  $modifiedPayload['account_id'],
                    'Cookie' => "X-Session-ID=" . /*$account->session_id*/ $modifiedPayload['account_id']
                ])
                ->async()
                ->baseUrl(env('PUPPETEER_API_URL'))
                ->post("/send-lead-message", [
                    'data' => $encryptedPayload
                ]);
            }

            // Ждем выполнения всех промисов
            $results = Utils::settle($promises)->wait();

            foreach ($results as $result) {
                if ($result['state'] === 'fulfilled') {
                    $response = $result['value'];
                    $message = ($response instanceof ConnectionException)
                        ? $response->getMessage()
                        : $response->body();
                    Log::channel('amocrm')->info("Запрос выполнен", ['response' => $message]);

                    if (method_exists($response, 'headers') && isset($response->headers()['Set-Cookie'])) {
                        foreach ($response->headers()['Set-Cookie'] as $cookie) {
                            if (preg_match('/X-Session-ID=([^;]+)/', $cookie, $matches)) {
                                $newSessionId = $matches[1];
                                if ($account->session_id !== $newSessionId) {
                                    $account->session_id = $newSessionId;
                                    $account->save();
                                    Log::channel('amocrm')->info("Обновлённая X-Session-ID сохранена в БД: {$newSessionId}");
                                }
                            }
                        }
                    }
                    $anyFulfilled = true;
                    break; // Если хоть один запрос успешен, прерываем цикл
                } else {
                    $error = $result['reason'];
                    if ($error instanceof RequestException && method_exists($error, 'response') && $error->response) {
                        $statusCode = $error->response->status();
                        $errorMessage = $error->response->body();
                    } elseif ($error instanceof ConnectionException) {
                        $statusCode = null;
                        $errorMessage = $error->getMessage();
                    } else {
                        $statusCode = null;
                        $errorMessage = $error->getMessage();
                    }

                    Log::error("Ошибка запроса (попытка " . ($attempt + 1) . ")", [
                        'status' => $statusCode,
                        'error' => $errorMessage
                    ]);

                    $lastErrorMessage = $errorMessage;
                }
            }

            if (!$anyFulfilled) {
                $attempt++;
                usleep(100000 * (2 ** $attempt)); // экспоненциальная задержка: 100мс, 200мс, 400мс, 800мс, 1600мс
            }
        }

        if (!$anyFulfilled) {
            throw new Exception("Превышено максимальное количество попыток отправки запроса. Последняя ошибка: " . $lastErrorMessage);
        }
    }


    /**
     * Отправляет запросы к Puppeteer-сервису с ретраями.
     */
    /*
    private function attemptSendToPuppeteer(array $payload, string $sessionId, AccountOAuth2 $account)
    {
        $maxAttempts = 5;
        $attempt = 0;

        $payload = $this->encryptData($payload);

        while ($attempt < $maxAttempts) {
            try {
                // Отправляем запрос к Puppeteer‑сервису асинхронно
                $promise = Http::withHeaders([
                    'Cookie' => "X-Session-ID={$sessionId}; Path=/; HttpOnly"
                ])
                ->async()
                ->baseUrl(env('PUPPETEER_API_URL'))
                ->post("/send-lead-message", [
                    'data' => $payload
                ]);

                // Обрабатываем промис без ожидания
                $promise->then(
                    function ($response) use ($account) {
                        Log::channel('amocrm')->info("Запрос выполнен", ['response' => $response->body()]);

                        // Если в ответе есть новый PUPPETEER_SESSION, обновляем его в БД
                        if (isset($response->headers()['Set-Cookie'])) {
                            foreach ($response->headers()['Set-Cookie'] as $cookie) {
                                if (preg_match('/X-Session-ID=([^;]+)/', $cookie, $matches)) {
                                    $newSessionId = $matches[1];
                                    // Обновляем поле session_id в БД
                                    $account->session_id = $newSessionId;
                                    $account->save();
                                    Log::channel('amocrm')->info("Обновлённая X-Session-ID сохранена в БД: {$newSessionId}");
                                }
                            }
                        }
                    },
                    function ($error) use ($attempt) {
                        $statusCode = $error instanceof RequestException ? $error->response->status() : null;

                        Log::error("Ошибка запроса", [
                            'attempt' => $attempt + 1,
                            'error' => $error instanceof RequestException
                                ? $error->response->body()
                                : $error->getMessage()
                        ]);

                        // Если ошибка 4xx (например, 401, 403, 404), нет смысла ретраить
                        if ($statusCode && $statusCode >= 400 && $statusCode < 500) {
                            throw new Exception("Ошибка запроса (код $statusCode), ретраить бессмысленно");
                        }
                    }
                );

                return; // Отправка завершена, выходим
            } catch (Exception $e) {
                Log::error('Ошибка запроса к Puppeteer API', ['attempt' => $attempt + 1, 'message' => $e->getMessage()]);
            }

            $attempt++;
            usleep(100000 * (2 ** $attempt)); // 100мс, 200мс, 400мс, 800мс, 1600мс
        }

        throw new Exception("Превышено максимальное количество попыток отправки запроса");
    }*/


}
