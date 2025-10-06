<?php

namespace App\Models\Voice;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

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
    protected $casts = [
        'user_id' => 'integer',
        'theme_id' => 'integer',
        'thread_id' => 'string',
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
            'thread_id' => ['required', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
