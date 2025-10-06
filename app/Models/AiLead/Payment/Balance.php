<?php

namespace App\Models\AiLead\Payment;

use Illuminate\Database\Eloquent\Model;
use App\Models\AiLead\Account\AccountOAuth2;

class Balance extends Model
{
    protected $fillable = [
        'client_id',
        'amount_rub',
        'margin_coefficient',
        'min_charge_rub',
        'low_balance_threshold',
    ];

    public function client()
    {
        return $this->belongsTo(AccountOAuth2::class, 'client_id', 'account_id');
    }
}
