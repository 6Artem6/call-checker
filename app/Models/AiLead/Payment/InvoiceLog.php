<?php

namespace App\Models\AiLead\Payment;

use Illuminate\Database\Eloquent\Model;

class InvoiceLog extends Model
{
    protected $fillable = [
        'invoice_id',
        'event',
        'payload'
    ];

    protected $casts = [
        'payload' => 'array'
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
