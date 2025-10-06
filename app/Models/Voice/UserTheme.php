<?php

namespace App\Models\Voice;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UserTheme extends Model
{
    protected $table = 'user_theme'; // Указание таблицы
    protected $primaryKey = ['user_id', 'theme_id'];
    public $timestamps = false; // Отключение полей created_at/updated_at, если их нет в таблице
    public $incrementing = false; // Отключение автоинкремента, так как PK составной

    /**
     * Массово заполняемые атрибуты
     */
    protected $fillable = [
        'user_id',
        'theme_id',
    ];
    protected $casts = [
        'user_id' => 'integer',
        'theme_id' => 'integer',
    ];

    /**
     * Валидация перед сохранением
     * @throws ValidationException
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $model->validate();
        });
    }

    /**
     * Правила валидации модели
     * @throws ValidationException
     */
    public function validate()
    {
        $validator = Validator::make($this->attributesToArray(), [
            'user_id' => ['required', 'integer'],
            'theme_id' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
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
