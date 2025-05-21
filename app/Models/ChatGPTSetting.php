<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ChatGPTSetting extends Model
{
    use HasFactory;

    protected $table = 'chat_gpt_settings';
    protected $primaryKey = 'setting_id';
    protected $fillable = [
        'account_id',
        'prompt',
        'temperature',
        'model',
        'assistant_id'
    ];
    protected $hidden = [
        'setting_id'
    ];
    protected $casts = [
        'setting_id' => 'integer',
        'account_id' => 'integer',
        'prompt' => 'string',
        'temperature' => 'float',
        'model' => 'string',
        'assistant_id' => 'string',
    ];

    public function files()
    {
        return $this->hasMany(ChatGPTFile::class, 'setting_id', 'setting_id');
    }

    public function account()
    {
        return $this->hasOne(AccountOAuth2::class, 'account_id', 'account_id');
    }

    public static function getModelList()
    {
        return Cache::remember('openai_model_lists', now()->addWeek(), static function () {
            $response = Http::baseUrl(env('OPENAI_API_URL'))
                ->withToken(env('OPENAI_API_KEY'))
                ->get('/models');
            return collect($response->json('data'))
                ->pluck('id')
                ->filter(fn($id) => str_contains($id, 'gpt'))
                ->sortBy('id')
                ->values()
                ->toArray();
        });
    }

    public function setAssistant()
    {
        $this->refresh();
        if (is_null($this->assistant_id)) {
            $this->createAssistant();
        } else {
            $this->updateAssistant();
        }
    }

    public function createAssistant()
    {
        $uploadedFiles = $this->files->pluck('file_id')->toArray();
        $response = Http::baseUrl(env('OPENAI_API_URL'))
            ->withHeaders([
                'OpenAI-Beta' => 'assistants=v2',
                'Content-Type' => 'application/json'
            ])
            ->withToken(env('OPENAI_API_KEY'))
            ->post('/assistants', [
                'name' => 'Ассистент #' . $this->account_id,
                'instructions' => $this->prompt,
                'model' => $this->model,
                'temperature' => (float) $this->temperature,
                'file_ids' => $uploadedFiles ?? null,
                'tool_resources' => [
                    'file_search' => ['enabled' => true]
                ],
                "tools" => ["type" => "file_search"],
            ]);
        $assistant_id = $response->json('id');
        ChatGPTSetting::updateOrCreate(
            ['setting_id' => $this->setting_id],
            ['assistant_id' => $assistant_id]
        );
    }

    public function updateAssistant()
    {
        if (!empty($this->assistant_id)) {
            $response = Http::baseUrl(env('OPENAI_API_URL'))
                ->withHeaders([
                    'OpenAI-Beta' => 'assistants=v2',
                    'Content-Type' => 'application/json'
                ])
                ->withToken(env('OPENAI_API_KEY'))
                ->get('/assistants/'. $this->assistant_id);
            if (empty($response->json())) {
                return $this->createAssistant();
            }
            $uploadedFiles = $this->files->pluck('file_id')->toArray();
            $response = Http::baseUrl(env('OPENAI_API_URL'))
                ->withHeaders([
                    'OpenAI-Beta' => 'assistants=v2',
                    'Content-Type' => 'application/json'
                ])
                ->withToken(env('OPENAI_API_KEY'))
                ->patch('/assistants/'. $this->assistant_id, [
                    'instructions' => $this->prompt,
                    'model' => $this->model,
                    'temperature' => (float) $this->temperature,
                    'file_ids' => $uploadedFiles ?? null,
                    'tool_resources' => [
                        'file_search' => ['enabled' => true]
                    ],
                    "tools" => ["type" => "file_search"],
                ]);
        }
    }
}
