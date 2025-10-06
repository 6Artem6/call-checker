<?php

namespace App\Models\AiLead\Payment;

use Illuminate\Database\Eloquent\Model;
use App\Models\AiLead\Account\AccountOAuth2;

class TokenUsage extends Model
{
    protected $fillable = [
        'client_id',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'usd_cost',
        'fx_used',
        'margin_used',
        'rub_cost',
        'message_id',
    ];

    public function client()
    {
        return $this->belongsTo(AccountOAuth2::class, 'client_id', 'account_id');
    }
}
