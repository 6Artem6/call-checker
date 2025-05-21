<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestFileStatus extends Model
{
    protected $table = 'request_file_status'; // Имя таблицы
    protected $primaryKey = 'status_id'; // Первичный ключ
    protected $fillable = [
        'status_name'
    ]; // Поля для массового заполнения

    /**
     * Правила валидации
     */
    public static function rules(): array
    {
        return [
            'status_id' => ['required', 'unique:request_file_status,status_id'],
            'status_name' => ['required', 'unique:request_file_status,status_name', 'string', 'max:32']
        ];
    }

    public static function attributeLabels()
    {
        return [
            'status_id' => __('Статус'),
            'status_name' => __('Название'),
        ];
    }

    public const STATUS_CREATED = 0;
    public const STATUS_BEGIN_TRANSCRIBE = 1;
    public const STATUS_END_TRANSCRIBE = 2;
    public const STATUS_BEGIN_ANALYSIS = 3;
    public const STATUS_END_ANALYSIS = 4;
    public const STATUS_ERROR_TRANSCRIBE = -1;
    public const STATUS_ERROR_ANALYSIS = -2;

}
