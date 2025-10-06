<?php

namespace App\Models\AiLead\Chat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

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
    protected $casts = [
        'domain' => 'string',
        'lead_id' => 'integer',
        'thread_id' => 'string',
        'status' => 'bool',
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
            'domain' => ['required', 'string', 'max:255'],
            'lead_id' => ['required', 'integer'],
            'thread_id' => ['string', 'max:50'],
            'status' => ['required', 'bool'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
