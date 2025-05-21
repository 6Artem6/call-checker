<?php

namespace App\Services;

use YooKassa\Client;
use App\Models\Invoice;

class YooKassaService
{
    protected Client $client;

    public function __construct(string $shopId, string $secretKey)
    {
        $this->client = new Client();
        $this->client->setAuth($shopId, $secretKey);
    }

    /**
     * Создаёт счёт и возвращает ссылку для оплаты.
     */
    public function createInvoice(Invoice $invoice): string
    {
        $response = $this->client->createPayment([
            'amount' => [
                'value'    => number_format($invoice->amount, 2, '.', ''),
                'currency' => $invoice->currency,
            ],
            'confirmation' => [
                'type'     => 'redirect',
                'return_url'=> route('payment.return', ['invoice' => $invoice->id]),
            ],
            'capture' => true,
            'description' => "Invoice #{$invoice->invoice_id}",
            'metadata' => [
                'invoice_id' => $invoice->invoice_id,
                'client_id'  => $invoice->client_id,
            ],
        ], uniqid());

        // Обновляем модель
        $invoice->payment_id   = $response->id;
        $invoice->payment_link = $response->confirmation->confirmation_url;
        $invoice->expires_at   = $response->expires_at;
        $invoice->save();

        return $invoice->payment_link;
    }
}
