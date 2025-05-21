<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SeleniumSession extends Model
{
    protected $table = 'selenium_sessions'; // Указание таблицы
    protected $primaryKey = ['node_port'];
    public $timestamps = false; // Отключение полей created_at/updated_at, если их нет в таблице
    public $incrementing = false; // Отключение автоинкремента, так как PK составной

    /**
     * Массово заполняемые атрибуты
     */
    protected $fillable = [
        'status',
    ];
    protected $hidden = [
        'node_port',
    ];
    protected $casts = [
        'node_port' => 'integer',
        'status' => 'integer',
    ];

    public const STATUS_BUZY = 0;
    public const STATUS_FREE = 1;
    public const STATUS_ERROR = -1;

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
            'node_port' => ['required', 'integer'],
            'status' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
