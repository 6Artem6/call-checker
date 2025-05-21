<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Theme extends Model
{
    protected $table = 'theme'; // Указание таблицы
    protected $primaryKey = 'theme_id'; // Указание первичного ключа
    public $timestamps = false; // Отключение полей created_at/updated_at, если их нет

    /**
     * Массово заполняемые атрибуты
     */
    protected $fillable = [
        'theme_id',
        'theme_name',
    ];

    /**
     * Правила валидации (опционально, можно вынести в Request или использовать где нужно)
     */
    public static function rules()
    {
        return [
            'theme_id' => ['required', 'integer', 'unique:theme,theme_id'],
            'theme_name' => ['required', 'string', 'max:64', 'unique:theme,theme_name'],
        ];
    }

    /**
     * Связь "многие ко многим" через таблицу user_theme
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_theme', 'theme_id', 'user_id');
    }

    /**
     * Связь "один ко многим" с таблицей user_theme
     */
    public function userThemes()
    {
        return $this->hasMany(UserTheme::class, 'theme_id', 'theme_id');
    }

    /**
     * Получение списка тем, привязанных к текущему пользователю
     */
    public static function getUserList()
    {
        $userId = auth()->id(); // Получение идентификатора текущего пользователя

        return self::query()
            ->whereHas('userThemes', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->get();
    }

    /**
     * Получение списка тем для select (формат: id => name)
     */
    public static function getUserSelectList()
    {
        $userId = auth()->id();

        return self::query()
            ->select('theme_id as id', 'theme_name as name')
            ->whereHas('userThemes', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->pluck('name', 'id')
            ->toArray();
    }
}
