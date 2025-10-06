<?php

namespace App\Observers;

use App\Models\AiLead\Payment\Invoice;
use App\Models\AiLead\Payment\Transaction;
use App\Services\BalanceService;
use App\Services\ModelPricingService;

class InvoiceObserver
{
    public function saved(Invoice $invoice): void
    {
        if ($invoice->status !== Invoice::STATUS_SUCCEEDED) {
            return;
        }

        $fxRate = app(ModelPricingService::class)->getUsdRubRate();
        $margin = config('finance.margin', 1.0);

        $usdAmount = $invoice->currency === 'USD'
            ? $invoice->amount
            : $invoice->amount / $fxRate;

        $rubAmount = $usdAmount * $fxRate * $margin;

        Transaction::create([
            'client_id'    => $invoice->account_id,
            'type'         => Transaction::TYPE_PAYMENT,
            'usd_cost'     => $usdAmount,
            'fx_used'      => $fxRate,
            'margin_used'  => $margin,
            'message_id'   => $invoice->invoice_id,
            'status'       => Transaction::STATUS_COMPLETED,
        ]);

        app(BalanceService::class)->apply($invoice->account_id, $rubAmount, $margin);
    }
}
