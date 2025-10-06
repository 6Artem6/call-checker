<?php

namespace App\Models\Voice;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RequestFileStatus extends Model
{
    protected $table = 'request_file_status'; // Имя таблицы
    protected $primaryKey = 'status_id'; // Первичный ключ
    protected $fillable = [
        'status_name'
    ];
    protected $casts = [
        'status_id' => 'integer',
        'status_name' => 'string',
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
            'status_id' => ['required', 'unique:request_file_status,status_id', 'integer'],
            'status_name' => ['required', 'unique:request_file_status,status_name', 'string', 'max:32']
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public const STATUS_CREATED = 0;
    public const STATUS_BEGIN_TRANSCRIBE = 1;
    public const STATUS_END_TRANSCRIBE = 2;
    public const STATUS_BEGIN_ANALYSIS = 3;
    public const STATUS_END_ANALYSIS = 4;
    public const STATUS_ERROR_TRANSCRIBE = -1;
    public const STATUS_ERROR_ANALYSIS = -2;

}
