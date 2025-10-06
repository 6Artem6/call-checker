<?php

namespace App\Models\AiLead\Payment;

use Illuminate\Database\Eloquent\Model;

class FxRate extends Model
{
    protected $fillable = [
        'usd_rub',
        'source',
    ];
}
