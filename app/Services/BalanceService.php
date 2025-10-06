<?php

namespace App\Services;

use App\Models\AiLead\Payment\Balance;
use Illuminate\Support\Facades\DB;

class BalanceService
{
    public function apply(int $clientId, float $rubAmount, float $margin): Balance
    {
        return DB::transaction(function () use ($clientId, $rubAmount, $margin) {
            $balance = Balance::lockForUpdate()->firstOrCreate(
                ['client_id' => $clientId],
                [
                    'amount_rub'           => 0,
                    'margin_coefficient'   => $margin,
                    'min_charge_rub'       => 0,
                    'low_balance_threshold'=> 0,
                ]
            );

            $balance->amount_rub += $rubAmount;
            $balance->save();

            return $balance;
        });
    }
}
