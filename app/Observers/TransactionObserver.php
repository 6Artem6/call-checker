<?php

namespace App\Observers;

use App\Models\AiLead\Payment\Transaction;
use App\Services\BalanceService;

class TransactionObserver
{
    public function created(Transaction $transaction): void
    {
        if ($transaction->type !== Transaction::TYPE_USAGE) {
            return;
        }

        $rubAmount = -($transaction->usd_cost * $transaction->fx_used * $transaction->margin_used);

        app(BalanceService::class)->apply($transaction->client_id, $rubAmount, $transaction->margin_used);
    }
}
