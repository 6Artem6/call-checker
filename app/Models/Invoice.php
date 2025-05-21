<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'domain',
        'amount',
        'currency',
        'invoice_id',
        'payment_id',
        'payment_link',
        'status',
        'expires_at'
    ];

    public const string STATUS_SUCCEEDED = 'succeeded';
    public const string STATUS_CANCELED = 'canceled';
    public const string STATUS_PENDING = 'pending';
    public const string STATUS_WAITING_FOR_CAPTURE = 'waiting_for_capture';

    public function client()
    {
        return $this->belongsTo(AccountOAuth2::class, 'account_id', 'id');
    }

    public function logs()
    {
        return $this->hasMany(InvoiceLog::class);
    }
}
