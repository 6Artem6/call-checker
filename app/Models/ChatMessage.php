<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $contact_id
 * @property integer $chat_id
 * @property string $text
 */
class ChatMessage extends Model
{
    protected $table = 'chat_messages';
    protected $fillable = [
        'contact_id',
        'chat_id',
        'text',
    ];
}
