<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class FileChunk extends Model
{
    protected $table = 'file_chunk'; // Имя таблицы
    protected $primaryKey = 'chunk_id'; // Первичный ключ
    public $incrementing = true; // Если первичный ключ не автоинкрементируемый
    protected $keyType = 'int'; // Тип ключа
    public $timestamps = false;
    protected $fillable = [
        'chunk_id',
        'start_time',
        'end_time',
        'speaker',
        'confidence',
        'file_id',
        'text',
    ];

    public static function rules(): array
    {
        return [
            'chunk_id' => ['unique:file_chunk,chunk_id'],
            'start_milliseconds' => ['required', 'integer'],
            'end_milliseconds' => ['required', 'integer'],
            'speaker' => ['required', 'integer'],
            'confidence' => ['required', 'integer'],
            'file_id' => ['required', 'integer'],
            'text' => ['required', 'string'],
        ];
    }

    /**
     * Атрибуты для отображения.
     */
    public static function attributeLabels(): array
    {
        return [
            'chunk_id' => __('№'),
        ];
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
