<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use YooKassa\Model\Notification\NotificationEventType;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request)
    {
        Log::error('Тело запроса: ' . json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $signature = $request->header('X-Request-Signature');
        $payload = $request->getContent();

        $secret = env('YOOKASSA_WEBHOOK_SECRET');

        $expected = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        if (!hash_equals($expected, $signature)) {
            Log::warning('Yookassa webhook signature mismatch.', [
                'received' => $signature,
                'expected' => $expected,
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $data = json_decode($payload, true);
        $paymentId = $data['object']['id'];

        // Проверка на дубль
        if (InvoiceLog::where('payment_id', $paymentId)
            ->where('event', $data['event'])->exists()) {
            return response()->json(['status' => 'already processed']);
        }

        // Логируем любой приходящий вебхук
        $invoice = Invoice::where('payment_id', $paymentId)->first();

        InvoiceLog::create([
            'invoice_id' => $invoice?->id,
            'payment_id' => $paymentId,
            'event'      => $data['event'],
            'payload'    => $data,
        ]);

        if ($invoice) {
            $invoice->status = $data['event'] === NotificationEventType::PAYMENT_SUCCEEDED ?
                                                Invoice::STATUS_SUCCEEDED : Invoice::STATUS_CANCELED;
            $invoice->save();
        }

        return response()->json(['status' => 'ok']);
    }
}
