<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestInstruction extends Model
{
    protected $table = 'request_instruction';
    protected $primaryKey = ['instruction_id', 'request_id'];
    public $timestamps = false;
    public $incrementing = false;

    /**
     * Атрибуты, которые можно массово заполнить
     */
    protected $fillable = [
        'instruction_id',
        'request_id',
    ];

    /**
     * Получение связанной инструкции
     */
    public function instruction()
    {
        return $this->belongsTo(Instruction::class, 'instruction_id', 'instruction_id');
    }

    /**
     * Правила валидации для модели
     */
    public static function rules()
    {
        return [
            'instruction_id' => [
                'required',
                'integer',
                'unique:request_instruction,instruction_id,NULL,NULL,request_id'
            ],
            'request_id' => [
                'required',
                'integer',
                'unique:request_instruction,request_id,NULL,NULL,instruction_id'
            ],
        ];
    }
}
