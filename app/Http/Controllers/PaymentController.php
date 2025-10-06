<?php

namespace App\Http\Controllers;

use App\Models\AiLead\Payment\Invoice;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use YooKassa\Client;

class PaymentController extends Controller
{
    public function create()
    {
        return Inertia::render('Payment/Create');
    }

    public function store(Request $request)
    {
        $client = new Client();
        $client->setAuth(env('YOOKASSA_WEBHOOK_SHOP_ID'), env('YOOKASSA_WEBHOOK_SECRET'));

        $invoice = Invoice::create([
            'account_id' => auth()->guard('api')->user()->oauth2->account_id,
            'amount' => $request->input('amount'),
            'status' => Invoice::STATUS_PENDING,
            'description' => $request->input('description'),
            'invoice_id' => Str::uuid()->toString(),
        ]);

        $email = $invoice->user->email ?? auth()->guard('api')->user()?->email ?? 'user@example.com';
        $name = $invoice->user->name ?? auth()->guard('api')->user()?->name ?? 'Имя Клиента';

        $payment = $client->createPayment([
            'amount' => [
                'value' => number_format($invoice->amount, 2, '.', ''),
                'currency' => 'RUB',
            ],
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => route('payment-return', ['invoice_id' => $invoice->invoice_id]),
            ],
            'capture' => true,
            'description' => 'Invoice #' . $invoice->id,
            'payment_method_data' => [
                'type' => 'bank_card',
            ],
            'receipt' => [
                'customer' => [
                    'email' => $email,
                    'full_name' => $name,
                ],
                'items' => [[
                    'description' => 'Оплата счёта #' . $invoice->id,
                    'quantity' => 1.0,
                    'amount' => [
                        'value' => number_format($invoice->amount, 2, '.', ''),
                        'currency' => 'RUB',
                    ],
                    'vat_code' => 1,
                    'payment_mode' => 'full_prepayment',
                    'payment_subject' => 'service',
                ]],
            ],
        ], uniqid('', true));

        $invoice->payment_id = $payment->getId();
        $invoice->save();

        return response()->json([
            'redirect_url' => $payment->getConfirmation()->getConfirmationUrl(),
        ]);
    }

    public function return(Request $request)
    {
        $invoice_id = $request->query('invoice_id');

        if (!$invoice_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Отсутствует идентификатор платежа. Пожалуйста, попробуйте еще раз.',
            ], 400);
        }

        // Авторизация клиента ЮKassa
        $client = new Client();
        $client->setAuth(env('YOOKASSA_WEBHOOK_SHOP_ID'), env('YOOKASSA_WEBHOOK_SECRET'));

        try {
            $invoice = Invoice::query()->where('invoice_id', $invoice_id)->first();
            // Получаем информацию о платеже
            $payment = $client->getPaymentInfo($invoice->payment_id);
            $status = $payment->getStatus();

            $invoice->status = $status === Invoice::STATUS_SUCCEEDED ?
                                Invoice::STATUS_SUCCEEDED : Invoice::STATUS_CANCELED;
            $invoice->save();

            return Inertia::render('Payment/Return', [
                'status' => $status,
                'payment_id' => $invoice->payment_id
            ]);
        } catch (Exception $e) {
            // Логируем и показываем ошибку
            Log::error('Ошибка при проверке платежа: ' . $e->getMessage());

            return Inertia::render('Payment/Return', [
                'status' => 'error',
                'message' => 'Не удалось получить информацию о платеже.',
            ]);
        }
    }
}
