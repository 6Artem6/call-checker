<?php

namespace App\Models\AiLead\Gpt;

use App\Models\AiLead\Gpt\Abstracts\BaseChatGPTFile;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class ChatGPTFile extends BaseChatGPTFile
{
    protected $table = 'chat_gpt_files';

    protected $fillable = [
        'file_id',
        'setting_id',
        'original_name',
        'stored_name',
    ];

    protected $casts = [
        'setting_id' => 'integer',
    ];

    public function setting(): BelongsTo
    {
        return $this->belongsTo(ChatGPTSetting::class, 'setting_id');
    }

    public static function saveFile(UploadedFile $file, int $setting_id): self
    {
        $hashName = sha1($file->getClientOriginalName() . time()) . '.' . $file->getClientOriginalExtension();

        $response = Http::baseUrl(env('OPENAI_API_URL'))
            ->withToken(env('OPENAI_API_KEY'))
            ->attach('file', file_get_contents($file->path()), $file->getClientOriginalName())
            ->post('/files', [
                'purpose' => 'assistants'
            ]);

        $file_id = $response->json('id');

        return self::create([
            'file_id' => $file_id,
            'setting_id' => $setting_id,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $hashName,
        ]);
    }

    public function deleteFile(): bool
    {
        Http::baseUrl(env('OPENAI_API_URL'))
            ->withToken(env('OPENAI_API_KEY'))
            ->withHeaders(['OpenAI-Beta' => 'assistants=v2'])
            ->delete("/vector_stores/{$this->setting->vector_store_id}/files/{$this->file_id}");

        Http::baseUrl(env('OPENAI_API_URL'))
            ->withToken(env('OPENAI_API_KEY'))
            ->delete("/files/{$this->file_id}");

        return $this->delete();
    }
}
