<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ChatGPTFile extends Model
{
    use HasFactory;

    protected $table = 'chat_gpt_files';
    protected $primaryKey = 'file_id';
    protected $fillable = [
        'file_id',
        'setting_id',
        'original_name',
        'stored_name',
        'path'
    ];
    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'file_id' => 'string',
        'setting_id' => 'integer',
        'original_name' => 'string',
        'stored_name' => 'string',
        'path' => 'string',
    ];

    public function setting(): BelongsTo
    {
        return $this->belongsTo(ChatGPTSetting::class, 'setting_id');
    }

    public static function saveFile(UploadedFile $file, int $setting_id): self
    {
        // Генерируем уникальное имя файла
        $hashName = sha1($file->getClientOriginalName() . time()) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('uploads', $hashName, 'public');

        $response = Http::baseUrl(env('OPENAI_API_URL'))
            ->withToken(env('OPENAI_API_KEY'))
            ->attach('file', file_get_contents($file->path()), $file->getClientOriginalName())
            ->post('/files', [
                'purpose' => 'assistants',
            ]);

        $file_id = $response->json('id');
        // Сохраняем путь в БД
        $model = ChatGPTFile::create([
            'file_id' => $file_id,
            'setting_id' => $setting_id,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $hashName,
            'path' => $path
        ]);

        return $model;
    }

    /**
     * Путь к файлу
     */
    public function getFilePath(): string
    {
        return 'uploads/' . $this->stored_name;
    }

    /**
     * Локальный путь к файлу
     */
    public function getLocalFilePath(): string
    {
        return Storage::disk('public')->path($this->getFilePath());
    }

    /**
     * Проверка существования файла
     */
    public function getIsFileExists(): bool
    {
        return Storage::disk('public')->exists($this->getFilePath());
    }

    /**
     * Проверка существования файла
     */
    public function deleteFile(): bool
    {
        Storage::disk('public')->delete($this->getFilePath());
        Http::baseUrl(env('OPENAI_API_URL'))
            ->withToken(env('OPENAI_API_KEY'))
            ->delete('/files/' . $this->file_id);
        return $this->delete();
    }

    /**
     * Генерация ссылки для скачивания файла
     */
    public function getUrl(bool $absolute = true): string
    {
        return route('panel-file-download', ['name' => $this->stored_name], $absolute);
    }
}
