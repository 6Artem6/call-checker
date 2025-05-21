<?php

namespace App\Http\Controllers;

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
        $this->apiUrl = env('AMOCRM_API_URL');
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
            ->get("{$this->apiUrl}/api/v4/leads");

        $deals = $response->json()['_embedded']['leads'] ?? [];

        return json_encode($deals, JSON_UNESCAPED_UNICODE);
    }

    public function dealDetail($dealId)
    {
        $response = Http::withHeaders($this->getAmoCrmHeaders())
            ->get("{$this->apiUrl}/api/v4/leads/{$dealId}/notes");

        $messages = $response->json()['_embedded']['notes'] ?? [];

        return json_encode($messages, JSON_UNESCAPED_UNICODE);
    }

    private function getConversationId($dealId)
    {
        $response = Http::withHeaders($this->getAmoCrmHeaders())
            ->get("{$this->apiUrl}/api/v4/leads/{$dealId}/notes");

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
        $dealId = $request->input('deal_id');
        $text = $request->input('text');
        $conversationId = "a2e8f53e-26ba-46be-8f5d-d53c7404dd8b";//$this->getConversationId($dealId);

//        print_r($conversationId);die;
        if (!$conversationId) {
            return back()->withErrors('ID чата не найден.');
        }

        $timestamp = time();
        $payload = [
            'event_type' => 'new_message',
            'payload' => [
                'timestamp' => $timestamp,
                'msgid' => "msg_" . $timestamp,
                'conversation_id' => $conversationId,
                'sender' => ['id' => 'web_app_id', 'name' => 'Web App'],
                'message' => ['type' => 'text', 'text' => $text],
                'silent' => false
            ]
        ];

        $requestBody = json_encode($payload);
        $checkSum = md5($requestBody);
        $data = implode("\n", [
            "POST",
            $checkSum,
            "application/json",
            $timestamp,
            "/v2/origin/custom/{$conversationId}"
        ]);
        $signature = hash_hmac('sha1', $data, $this->secretKey);

        $headers = [
            'Content-Type' => 'application/json',
            'Date' => gmdate("D, d M Y H:i:s T"),
            'Content-MD5' => $checkSum,
            'X-Signature' => $signature,
        ];

        $response = Http::withHeaders($headers)
            ->post("{$this->apiUrl}/v2/origin/custom/{$conversationId}", $payload);

        if ($response->ok()) {
            return redirect()->route('deal.detail', ['dealId' => $dealId]);
        }

        $requestBody = [
            'account_id' => 'dfe7bb1a-0cda-4dbc-a66d-cea3063cec60',
            'title' => 'ChatsIntegration',
            'hook_api_version' => 'v2',
        ];
        $jsonBody = json_encode($requestBody, JSON_THROW_ON_ERROR);
        $checkSum = md5($jsonBody);
//        <script>(function(a,m,o,c,r,m){a[m]={id:"422221",hash:"a55733d8443fc0b8d2784551c009126dfc8b0a2fb545fba09baf44c834f02685",locale:"ru",inline:true,setMeta:function(p){this.params=(this.params||[]).concat([p])}};a[o]=a[o]||function(){(a[o].q=a[o].q||[]).push(arguments)};a[o+'Config']=a[o+'Config']||{};a[o+'Config'].hidden=!0;var d=a.document,s=d.createElement('script');s.async=true;s.id=m+'_script';s.src='https://gso.amocrm.ru/js/button.js';d.head&&d.head.appendChild(s)}(window,0,'amoSocialButton',0,0,'amo_social_button'));</script>
        $timestamp = time();
        $url = "/v2/origin/custom/422221/connect";
        $data = implode("\n", [
            "POST",
            $checkSum,
            "application/json",
            $timestamp,
            $url
        ]);
        $signature = hash_hmac('sha1', $data, "a55733d8443fc0b8d2784551c009126dfc8b0a2fb545fba09baf44c834f02685");
        $headers = [
            'Content-Type' => 'application/json',
            'Date' => gmdate("D, d M Y H:i:s T"),
            'Content-MD5' => $checkSum,
            'X-Signature' => $signature,
        ];
        $response = Http::withHeaders($headers)
            ->post($this->apiUrl . $url, $requestBody);
        print_r($response->body()); die();
    }
}
