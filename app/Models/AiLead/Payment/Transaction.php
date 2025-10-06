<?php

namespace App\Models\AiLead\Payment;

use Illuminate\Database\Eloquent\Model;
use App\Models\AiLead\Account\AccountOAuth2;

class Transaction extends Model
{
    protected $fillable = [
        'client_id',
        'type',
        'usd_cost',
        'fx_used',
        'margin_used',
        'message_id',
        'status',
    ];

    // 🔹 Статусы
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PENDING   = 'pending';
    public const STATUS_FAILED    = 'failed';

    // 🔹 Типы транзакций
    public const TYPE_PAYMENT      = 'payment';      // пополнение (invoice)
    public const TYPE_USAGE        = 'usage';        // расход (запросы к моделям)
    public const TYPE_SUBSCRIPTION = 'subscription'; // списание за тариф
    public const TYPE_REFUND       = 'refund';       // возврат

    public function client()
    {
        return $this->belongsTo(AccountOAuth2::class, 'client_id', 'account_id');
    }
}
