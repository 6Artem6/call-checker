<?php

namespace App\Models;

use Aws\S3\S3Client;
use getID3;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;


class File extends Model
{
    protected $table = 'file'; // Имя таблицы
    protected $primaryKey = 'file_id'; // Первичный ключ
    protected $fillable = [
        'file_id',
        'file_name',
        'file_ext',
        'file_size',
        'file_time',
        'file_hash',
        'file_system_name',
        'request_id',
        'status_id',
    ]; // Поля для массового заполнения

    public $timestamps = false;

    /**
     * Правила валидации
     */
    public static function rules(): array
    {
        return [
            'file_id' => ['unique:file,file_id'],
            'file_name' => ['required', 'string', 'max:1024'],
            'file_ext' => ['required', 'string', 'max:6'],
            'file_size' => ['required', 'integer'],
            'file_time' => ['required', 'integer'],
            'file_hash' => ['required', 'string', 'max:40'],
            'file_system_name' => ['required', 'string', 'max:256'],
            'request_id' => ['required', 'integer'],
            'status_id' => ['required', 'integer'],
        ];
    }

    /**
     * Отношение к модели Request
     */
    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id', 'request_id');
    }

    /**
     * Отношение к модели FileAnalysis
     */
    public function analysis()
    {
        return $this->hasOne(FileAnalysis::class, 'file_id', 'file_id');
    }

    /**
     * Отношение к модели FileChunk
     */
    public function chunks()
    {
        return $this->hasMany(FileChunk::class, 'file_id', 'file_id');
    }

    /**
     * Отношение к модели RequestFileStatus
     */
    public function status()
    {
        return $this->belongsTo(RequestFileStatus::class, 'status_id', 'status_id');
    }

    /**
     * Сохранение данных файла
     */
    public function saveData(int $request_id, UploadedFile $file): bool
    {
        $this->request_id = $request_id;
        $this->file_name = $file->getClientOriginalName();
        $this->file_ext = $file->getClientOriginalExtension();
        $this->file_size = $file->getSize();
        $this->file_hash = sha1_file($file->getRealPath());
        $this->file_system_name = uniqid('', true) . '.' . $this->file_ext;

        $filePath = $file->getRealPath();
        $fileInfo = (new getID3())->analyze($filePath);
        if (!isset($fileInfo['error']) and isset($fileInfo['playtime_seconds'])) {
            $this->file_time = round($fileInfo['playtime_seconds']);
        }

        if ($this->save()) {
            $path = $this->getFilePath();
            Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));
            return true;
        }
        return false;
    }

    public static function getUserFile(int $id)
    {
        return self::with('request')
            ->where('file.file_id', $id)
            ->whereHas('request', function ($query) {
                $query->where('user_id', auth()->id()); // Условие на текущего пользователя
            })
            ->first();
    }

    public static function getUserRecord(int $id)
    {
        return self::with(['request', 'analysis'])
            ->where('file.file_id', $id)
            ->whereHas('request', function ($query) {
                $query->where('user_id', auth()->id()); // Условие на текущего пользователя
            })
            ->first();
    }

    public static function getViewAnalysisRecord(int $id)
    {
        return self::with([
            'request.instructions.instruction', // Вложенные связи
            'analysis',
            'chunks',
        ])
            ->where('file_id', $id) // Условие для поиска записи по file_id
            ->whereHas('request', function ($query) {
                $query->where('user_id', auth()->id()); // Условие на user_id
            })
            ->first(); // Получаем одну запись
    }

    /**
     * Путь к файлу
     */
    public function getFilePath(): string
    {
        return 'repository/files/' . $this->file_system_name;
    }

    /**
     * Локальный путь к файлу
     */
    public function getLocalFilePath(): string
    {
        return Storage::disk('local')->path($this->getFilePath());
    }

    /**
     * Проверка существования файла
     */
    public function getIsFileExists(): bool
    {
        return file_exists($this->getLocalFilePath());
    }

    /**
     * Генерация ссылки для скачивания файла
     */
    public function getUrl(): string
    {
        return route('file-info', ['id' => $this->file_id]);
    }

    public function getViewHtml()
    {
        $url = $this->getUrl();
        return  "<audio controls>" .
                "<source src=\"{$url}\" type=\"audio/mpeg\">" .
                "</audio>";
    }
}
