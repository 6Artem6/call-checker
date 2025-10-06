<?php

namespace App\Models\Voice;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FileChunk extends Model
{
    protected $table = 'file_chunk'; // Имя таблицы
    protected $primaryKey = 'chunk_id'; // Первичный ключ
    public $timestamps = false;
    protected $fillable = [
        'start_time',
        'end_time',
        'speaker',
        'confidence',
        'file_id',
        'text',
    ];
    protected $hidden = [
        'chunk_id'
    ];
    protected $casts = [
        'chunk_id' => 'integer',
        'start_milliseconds' => 'integer',
        'end_milliseconds' => 'integer',
        'speaker' => 'integer',
        'confidence' => 'integer',
        'file_id' => 'integer',
        'text' => 'string',
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
            'chunk_id' => ['unique:file_chunk,chunk_id'],
            'start_milliseconds' => ['required', 'integer'],
            'end_milliseconds' => ['required', 'integer'],
            'speaker' => ['required', 'integer'],
            'confidence' => ['required', 'integer'],
            'file_id' => ['required', 'integer'],
            'text' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Метод для сохранения данных.
     *
     * @param int $file_id
     * @param array $data
     * @return bool
     */
    public function saveData(int $file_id, array $data): bool
    {
        $this->text = (string) $data['text'];
        $this->start_milliseconds = (int)$data['start_milliseconds'];
        $this->end_milliseconds = (int) $data['end_milliseconds'];
        $this->speaker = (int) $data['speaker'];
        $this->confidence = (int) $data['confidence'];
        $this->file_id = $file_id;

        return $this->save();
    }
}
