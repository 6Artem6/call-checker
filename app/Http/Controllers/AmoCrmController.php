<?php

namespace App\Http\Controllers;

use App\Models\AiLead\Chat\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AmoCrmController extends Controller
{
    private $apiUrl;
    private $accessToken;
    private $secretKey;
    private $clientId;

    public function __construct()
    {
        $this->apiUrl = "https://amojo.amocrm.ru";//env('AMOCRM_API_URL');
        $this->accessToken = env('AMOCRM_ACCESS_TOKEN');
        $this->secretKey = env('AMOCRM_CLIENT_SECRET');
        $this->clientId = env('AMOCRM_CLIENT_ID');
    }

    private function getAmoCrmHeaders()
    {
        return [
            'Authorization' => "Bearer {$this->accessToken}",
            'Content-Type' => 'application/json',
        ];
    }

    public function leads()
    {
        $response = Http::withHeaders($this->getAmoCrmHeaders())
            ->baseUrl($this->apiUrl)
            ->get("/api/v4/leads");

        $deals = $response->json()['_embedded']['leads'] ?? [];

        return json_encode($deals, JSON_UNESCAPED_UNICODE);
    }

    public function dealDetail($dealId)
    {
        $response = Http::withHeaders($this->getAmoCrmHeaders())
            ->baseUrl($this->apiUrl)
            ->get("/api/v4/leads/{$dealId}/notes");

        $messages = $response->json()['_embedded']['notes'] ?? [];

        return json_encode($messages, JSON_UNESCAPED_UNICODE);
    }

    private function getConversationId($dealId)
    {
        $response = Http::withHeaders($this->getAmoCrmHeaders())
            ->baseUrl($this->apiUrl)
            ->get("/api/v4/leads/{$dealId}/notes");

        if ($response->ok()) {
            $notes = $response->json() ?? [];
            print_r($notes); die();
            foreach ($notes as $note) {
                if ($note['note_type'] === 'service_message') {
                    return $note['conversation_id'];
                }
            }
        }
        return null;
    }

    public function sendMessage(Request $request)
    {

//        $payload = [
//            'event_type' => 'new_message',
//            'payload' => [
//                'timestamp' => $timestamp,
//                'msgid' => "msg_" . $timestamp,
//                'conversation_id' => $conversationId,
//                'sender' => ['id' => 'web_app_id', 'name' => 'Web App'],
//                'message' => ['type' => 'text', 'text' => $text],
//                'silent' => false
//            ]
//        ];


        $requestBody = [
            'event_type' => 'new_message',
            'payload' => [
                'timestamp' => time(),
                'msec_timestamp' => (int)(microtime(true) * 1000),
                'msgid' => uniqid('msgid_', true),
                'conversation_id' => '8fe33416-d781-456c-ae29-38bd3ef7a6f9',// env("AMOCRM_CHANNEL_ID"),
                'conversation_ref_id' => 'e4e95649-24a1-49b9-92a9-02128ba8e32b',
                'origin' => 'ru.voiceleadai',
                'sender' => [
                    'id' => env("AMOCRM_CHANNEL_ID"),
                    'name' => 'Test user',
                ],
//                'receiver' => [
//                    'id' => env("AMOCRM_CHANNEL_ID"),
//                    'name' => 'Артём',
//                ],
                'message' => [
                    'type' => 'text',
                    'text' => 'Test text 4',
                ],
            ],
        ];

//        $requestBody = [
//            'account_id' => env("AMOCRM_ACCOUNT_ID"),
//            'title' => 'ChatsIntegration',
//            'hook_api_version' => 'v2',
//        ];

        $jsonBody = json_encode($requestBody, JSON_THROW_ON_ERROR);
        $checkSum = md5($jsonBody);

        $timestamp = gmdate("D, d M Y H:i:s T");
        $url = "/v2/origin/custom/" . env("AMOCRM_SCOPE_ID"); //. '/chats/' . '69d5d2a4-ab60-49dd-bffd-ee90de138359' . '/history' ; // env("AMOCRM_CHANNEL_ID") . "/connect";

        $data = implode("\n", [
            "POST",
            $checkSum,
            "application/json",
            $timestamp,
            $url
        ]);
        $signature = hash_hmac('sha1', $data, env("AMOCRM_CHANNEL_SECRET"));
        $headers = [
            'Content-Type' => 'application/json',
            'Date' => $timestamp,
            'Content-MD5' => $checkSum,
            'X-Signature' => $signature,
        ];
        $response = Http::withHeaders($headers)
            ->baseUrl($this->apiUrl)
            ->post($url, $requestBody);
        print_r($response->body()); die();
    }

    public function testSendMessage(Request $request)
    {
        $model = new ChatMessage;
        $model->saveAnswerAnalysis();
    }


    public function testRequest(Request $request)
    {
        $username = 'kirill.tihiy@mail.ru';
        $password = '725513';
        $domain = 'kirilltihiy.amocrm.ru';
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        $cookies_domain = [
            'displayed_widgets_count_' => '0',
            'fb_dp' => '1',
            'gso_visitor_uid' => 'aa9b22ef-628d-51b8-9040-0b26e777e431',
            'last_login' => '',
            'LAST_PLACE' => 'dashboard',
            'LAST_PLACE_CONTACTS' => 'list/companies',
            'LAST_PLACE_DEALS' => 'pipeline',
            'LAST_PLACE_REPORTS' => 'pipeline',
            'LAST_PLACE_TODO' => 'calendar/month',
            'user_lang' => 'ru',
        ];
        $response = Http::withHeaders($headers)
            ->get('https://kirilltihiy.amocrm.ru/');
        $cookies = $response->cookies();
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === 'session_id') {
                $cookies_domain['session_id'] = $cookie->getValue();
            } elseif ($cookie->getName() === 'csrf_token') {
                $cookies_domain['csrf_token'] = $cookie->getValue();
            }
        }

        $response = Http::withHeaders($headers)
            ->withCookies($cookies_domain, $domain)
            ->post('https://kirilltihiy.amocrm.ru/oauth2/authorize', [
                'csrf_token' => $cookies_domain['csrf_token'],
                'username' => $username,
                'password' => $password,
                'temporary_auth' => 'N',
            ]);
        $access_token = json_decode($response->body(), true)['access_token'];

        $domain_all = ".amocrm.ru";
        $cookies_domain_all = [
            'access_token' => $access_token,
        ];
        
        $response = Http::withHeaders($headers)
//            ->withCookies($cookies_domain, $domain)
            ->withCookies($cookies_domain_all, $domain_all)
            ->get('https://kirilltihiy.amocrm.ru/ajax/v4/leads/31042357/chats');
        print_r($response->body());
    }
}
