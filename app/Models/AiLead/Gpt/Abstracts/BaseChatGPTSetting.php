<?php

namespace App\Models\AiLead\Gpt\Abstracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Http;

abstract class BaseChatGPTSetting extends Model
{
    // Первичный ключ и стратегии Eloquent…
    protected $primaryKey = 'setting_id';
    public $incrementing = true;
    protected $keyType = 'integer';

    // Общие поля
    protected $fillable = [
        'account_id',
        'prompt',
        'completion_condition',
        'temperature',
        'model',
        // специфичные – в наследниках
    ];

    protected $casts = [
        'setting_id'  => 'integer',
        'account_id'  => 'integer',
        'prompt'      => 'string',
        'completion_condition'      => 'string',
        'temperature' => 'float',
        'model'       => 'string',
    ];

    /**
     * Файлы ассистента — связь «1 ко многим»
     */
    abstract public function files(): HasMany;

    /**
     * Владелец ассистента — связь «1 к 1»
     */
    abstract public function account(): HasOne;

    /**
     * Создать/обновить ассистента у провайдера (OpenAI и т.п.)
     */
    abstract public function setAssistant(): mixed;

    /**
     * Удалить ассистента у провайдера
     */
    abstract public function deleteAssistant(): bool;

    /**
     * Общий HTTP‑клиент (можно переопределить для другого провайдера)
     */
    protected static function baseRequest()
    {
        return Http::baseUrl(config('services.openai.api_url'))
            ->withToken(config('services.openai.api_key'))
            ->withHeaders([
                'Content-Type'    => 'application/json',
                'OpenAI-Beta'     => 'assistants=v2',
            ]);
    }
}
