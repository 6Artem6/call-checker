<?php

namespace App\Models\Voice;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

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
    protected $casts = [
        'instruction_id' => 'integer',
        'request_id' => 'integer',
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
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Получение связанной инструкции
     */
    public function instruction()
    {
        return $this->belongsTo(Instruction::class, 'instruction_id', 'instruction_id');
    }
}
