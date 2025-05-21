<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTheme extends Model
{
    protected $table = 'user_theme'; // Указание таблицы
    public $timestamps = false; // Отключение полей created_at/updated_at, если их нет в таблице
    public $incrementing = false; // Отключение автоинкремента, так как PK составной

    /**
     * Массово заполняемые атрибуты
     */
    protected $fillable = [
        'user_id',
        'theme_id',
    ];

    /**
     * Правила валидации (вручную)
     */
    public static function rules()
    {
        return [
            'user_id' => ['required', 'integer'],
            'theme_id' => ['required', 'integer'],
        ];
    }

    /**
     * Установка связи с моделью User
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Установка связи с моделью Theme
     */
    public function theme()
    {
        return $this->belongsTo(Theme::class, 'theme_id', 'theme_id');
    }
}
