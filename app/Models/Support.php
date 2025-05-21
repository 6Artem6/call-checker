<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Support extends Model
{
    protected $fillable = [
        'email',
        'subject',
        'message',
        'feedback',
        'feedback_sent_at',
    ];

    protected $dates = [
        'feedback_sent_at',
    ];
}
