<?php

namespace App\Models\AiLead\Gpt;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\AiLead\Gpt\Abstracts\BaseChatGPTSetting;
use App\Models\AiLead\Account\AccountOAuth2;

class ChatGPTSetting extends BaseChatGPTSetting
{
    use HasFactory;

    protected $table = 'chat_gpt_settings';
    protected $primaryKey = 'setting_id';
    protected $fillable = [
        'account_id',
        'prompt',
        'completion_condition',
        'delay',
        'temperature',
        'model',
        'assistant_id',
        'vector_store_id',
        'is_active',
    ];
    protected $hidden = [
        'setting_id'
    ];
    protected $casts = [
        'setting_id' => 'integer',
        'account_id' => 'integer',
        'prompt' => 'string',
        'completion_condition' => 'string',
        'delay' => 'integer',
        'temperature' => 'float',
        'model' => 'string',
        'assistant_id' => 'string',
        'vector_store_id' => 'string',
        'is_active' => 'boolean',
    ];

    public function files(): HasMany
    {
        return $this->hasMany(ChatGPTFile::class, 'setting_id', 'setting_id');
    }

    public function account(): HasOne
    {
        return $this->hasOne(AccountOAuth2::class, 'account_id', 'account_id');
    }

    public static function getModelList(): array
    {
        return Cache::remember('openai_model_lists', now()->addWeek(), function () {
            $response = self::baseRequest()->get('/models');

            $exclude = ['vision', 'embedding', 'tts', 'whisper', 'audio'];

            return collect($response->json('data'))
                ->pluck('id')
                ->filter(function ($id) use ($exclude) {
                    // включаем, если содержит gpt или o1/o3/o4
                    $isTextModel = str_contains($id, 'gpt') || str_contains($id, 'o1') || str_contains($id, 'o3') || str_contains($id, 'o4');
                    // исключаем по ключевым словам
                    $isExcluded = collect($exclude)->some(fn($needle) => str_contains($id, $needle));
                    return $isTextModel && !$isExcluded;
                })
                ->sort()
                ->values()
                ->toArray();
        });
    }

    public function setAssistant(): mixed
    {
        $this->refresh();

        if (empty($this->assistant_id) || empty($this->vector_store_id) ||
            is_null($this->assistant_id) || is_null($this->vector_store_id)) {
            return $this->createAssistant();
        }

        return $this->updateAssistant();
    }

    public function deleteAssistant(): bool
    {
        if (empty($this->assistant_id)) {
            return false;
        }

        self::baseRequest()
            ->delete("/assistants/{$this->assistant_id}");

        return true;
    }

    public function createAssistant()
    {
        $vectorStore = self::baseRequest()->post('/vector_stores')->json();
        $vector_store_id = $vectorStore['id'];

        $assistant = self::baseRequest()->post('/assistants', [
            'name' => 'Ассистент #' . $this->account_id,
            'instructions' => $this->prompt,
            'model' => $this->model,
            // 'temperature' => $this->temperature,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'response_schema',
                    'description' => 'Схема ответа по заказу',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => [
                                'type' => 'string',
                                'description' => 'Сообщение о статусе заказа или инструкции для клиента.'
                            ],
                            'status' => [
                                'type' => 'number',
                                'description' => 'Код статуса (1 — диалог продолжается, 2 — заказ завершён по условиям из файлов, 0 — ошибка условий из файлов).'
                            ],
                            'data' => [
                                'type' => 'array',
                                'description' => 'Дополнительные данные о клиенте и заказе.',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => [
                                            'type' => 'string',
                                            'description' => 'Название параметра.'
                                        ],
                                        'value' => [
                                            'type' => ['string', 'null'],
                                            'description' => 'Значение параметра.'
                                        ],
                                    ],
                                    'required' => ['name', 'value'],
                                    'additionalProperties' => false
                                ],
                                'default' => []
                            ],
                            'items' => [
                                'type' => 'array',
                                'description' => 'Список товаров в заказе.',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => ['type' => 'string', 'description' => 'Название товара.'],
                                        'description' => ['type' => 'string', 'description' => 'Дополнительное описание.'],
                                        'count' => ['type' => 'number', 'description' => 'Количество товара.'],
                                        'price_per_unit' => ['type' => 'number', 'description' => 'Цена за единицу.'],
                                        'total_price' => ['type' => 'number', 'description' => 'Общая стоимость.']
                                    ],
                                    'required' => ['name', 'description', 'count', 'price_per_unit', 'total_price'],
                                    'additionalProperties' => false
                                ],
                                'default' => []
                            ],
                            // Новое поле tasks для передачи задач оператору
                            'tasks' => [
                                'type' => 'array',
                                'description' => 'Массив задач для оператора в случае, если ассистент не смог обработать запрос самостоятельно.',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'type' => [
                                            'type' => 'string',
                                            'description' => 'Тип задачи, например "unhandled_question".'
                                        ],
                                        'description' => [
                                            'type' => 'string',
                                            'description' => 'Пояснение оператору, зачем нужна эта задача.'
                                        ],
                                        'original_question' => [
                                            'type' => 'string',
                                            'description' => 'Оригинальный вопрос пользователя.'
                                        ],
                                    ],
                                    'required' => ['type', 'description', 'original_question'],
                                    'additionalProperties' => false
                                ],
                                'default' => []
                            ],
                            'total_order_price' => [
                                'type' => ['number', 'null'],
                                'description' => 'Общая стоимость заказа. Если заказа нет, должно быть null.',
                                'default' => null
                            ],
                            'error' => [
                                'type' => ['string', 'null'],
                                'description' => 'Сообщение об ошибке, если есть.',
                                'default' => null
                            ],
                        ],
                        'required' => ['text', 'status', 'data', 'items', 'total_order_price', 'error'],
                        'additionalProperties' => false
                    ]
                ]
            ],
            'tools' => [
                ['type' => 'file_search'],
            ],
            'tool_resources' => [
                'file_search' => [
                    'vector_store_ids' => [$vector_store_id],
                ],
            ],
        ])->json();

        $assistant_id = $assistant['id'] ?? null;
        if (!$assistant_id) {
            Log::channel('amocrm')->info("\$assistant is empty - " . json_encode($assistant));
        }

        $systemFiles = ChatGPTSystemFile::query()->pluck('file_id')->toArray();
        $uploadedFiles = $this->files->pluck('file_id')->toArray();
        foreach (array_merge($systemFiles, $uploadedFiles) as $file_id) {
            self::baseRequest()->post("/vector_stores/{$vector_store_id}/files", [
                'file_id' => $file_id
            ]);
        }

        $this->update([
            'assistant_id' => $assistant_id,
            'vector_store_id' => $vector_store_id
        ]);
    }


    public function updateAssistant()
    {
        if (empty($this->assistant_id)) {
            return $this->createAssistant();
        }

        $response = self::baseRequest()->get("/assistants/{$this->assistant_id}");
        if (empty($response->json())) {
            return $this->createAssistant();
        }

        $r = self::baseRequest()->post("/assistants/{$this->assistant_id}", [
            'instructions' => $this->prompt,
            'model' => $this->model,
            // 'temperature' => $this->temperature,
            "tools" => [
                ["type" => "file_search"]
            ],
            'tool_resources' => [
                'file_search' => [
                    'vector_store_ids' => [$this->vector_store_id],
                ],
            ],
        ]);

        $existingFiles = self::baseRequest()
            ->get("/vector_stores/{$this->vector_store_id}/files")
            ->json('data', []);

        $existingFileIds = collect($existingFiles)->pluck('id')->toArray();
        $uploadedFiles = $this->files->pluck('file_id')->toArray();

        $newFiles = array_diff($uploadedFiles, $existingFileIds);

        foreach ($newFiles as $file_id) {
            self::baseRequest()->post("/vector_stores/{$this->vector_store_id}/files", [
                'file_id' => $file_id
            ]);
        }
    }
}
