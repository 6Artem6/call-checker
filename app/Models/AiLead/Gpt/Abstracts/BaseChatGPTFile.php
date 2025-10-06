<?php

namespace App\Models\AiLead\Gpt\Abstracts;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Http;

abstract class BaseChatGPTFile extends Model
{
    protected $primaryKey = 'file_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'file_id',
        'original_name',
        'stored_name',
    ];

    protected $casts = [
        'file_id' => 'string',
        'original_name' => 'string',
        'stored_name' => 'string',
    ];

    abstract public function deleteFile(): bool;

    public function getUrl(bool $absolute = true): string
    {
        return route('panel-file-download', ['name' => $this->stored_name], $absolute);
    }

    public function streamFromOpenAI(): StreamedResponse
    {
        $stream = function () {
            echo Http::baseUrl(env('OPENAI_API_URL'))
                ->withToken(env('OPENAI_API_KEY'))
                ->get("/files/{$this->file_id}/content")
                ->body();
        };

        return response()->stream($stream, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => "attachment; filename=\"{$this->original_name}\"",
        ]);
    }
}
