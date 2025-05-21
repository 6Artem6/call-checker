<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserChatThread extends Model
{
    protected $table = 'user_chat_thread'; // Указание таблицы
    protected $primaryKey = [
        'domain',
        'lead_id'
    ];
    public $timestamps = false; // Отключение полей created_at/updated_at, если их нет в таблице
    public $incrementing = false; // Отключение автоинкремента, так как PK составной
    protected $keyType = 'string';

    /**
     * Массово заполняемые атрибуты
     */
    protected $fillable = [
        'domain',
        'lead_id',
        'thread_id',
        'status',
    ];

    /**
     * Правила валидации (вручную)
     */
    public static function rules()
    {
        return [
            'domain' => ['required', 'string', 'max:255'],
            'lead_id' => ['required', 'integer'],
            'thread_id' => ['required', 'string', 'max:50'],
            'status' => ['required', 'bool'],
        ];
    }
}
