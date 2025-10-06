<?php

namespace App\Models\AiLead;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SeleniumSession extends Model
{
    protected $table = 'selenium_sessions'; // Указание таблицы
    protected $primaryKey = 'account_id';
    public $timestamps = false; // Отключение полей created_at/updated_at, если их нет в таблице
    public $incrementing = false; // Отключение автоинкремента

    /**
     * Массово заполняемые атрибуты
     */
    protected $fillable = [
        'account_id',
        'status',
        'webdriver_session',
    ];
    protected $casts = [
        'account_id' => 'integer',
        'status' => 'integer',
        'webdriver_session' => 'string',
    ];
    protected $guarded = [];

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
            'account_id' => ['integer'],
            'status' => ['integer', 'in:' . implode(',', [
                    static::STATUS_BUZY, static::STATUS_FREE, static::STATUS_ERROR
                ])
            ],
            'webdriver_session' => ['string', 'nullable']
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
