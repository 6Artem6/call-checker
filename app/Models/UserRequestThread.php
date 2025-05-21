<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRequestThread extends Model
{
    protected $table = 'user_request_thread'; // Указание таблицы
    protected $primaryKey = ['user_id', 'theme_id'];
    public $timestamps = false; // Отключение полей created_at/updated_at, если их нет в таблице
    public $incrementing = false; // Отключение автоинкремента, так как PK составной

    /**
     * Массово заполняемые атрибуты
     */
    protected $fillable = [
        'user_id',
        'theme_id',
        'thread_id',
    ];

    /**
     * Правила валидации (вручную)
     */
    public static function rules()
    {
        return [
            'user_id' => ['required', 'integer'],
            'theme_id' => ['required', 'integer'],
            'thread_id' => ['required', 'string', 'max:50'],
        ];
    }
}
