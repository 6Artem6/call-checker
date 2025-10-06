<?php

namespace App\Services\OpenAI;

use App\Services\OpenAI\Abstracts\AbstractOpenAIService;
use Illuminate\Support\Facades\Log;

class OpenAIArbitrationService extends AbstractOpenAIService
{
    public function arbitrateCondition(string $condition, array $dialog): array
    {
        $input = [
            'condition' => $condition,
            'dialog'    => $dialog,
        ];

        $jsonSchema = [
            'name'   => 'DialogCompletionVerdict',
            'schema' => [
                'type'       => 'object',
                'properties' => [
                    'verdict' => [
                        'type'        => 'boolean',
                        'description' => 'true — условия выполнены, false — условия не выполнены.'
                    ],
                    'missing' => [
                        'type'        => 'array',
                        'items'       => ['type' => 'string'],
                        'description' => 'Перечень недостающих пунктов. Если ничего не не хватает, пустой массив.'
                    ],
                    'notes' => [
                        'type'        => 'string',
                        'description' => 'Краткое пояснение (1–2 предложения).'
                    ],
                ],
                'required'             => ['verdict', 'missing', 'notes'],
                'additionalProperties' => false,
            ],
        ];

        // Системная инструкция
        $systemPrompt = <<<EOT
Ты — строгий арбитр соответствия условий завершения диалога.
ТВОЯ ЗАДАЧА: по предоставленному JSON с «Условием» и «Диалогом» решить, можно ли завершить бота сейчас.

Правила:

1. Опираться ТОЛЬКО на факты из диалога. Не додумывать.
2. «Условие» задаётся пользователем в свободной форме и является ЕДИНСТВЕННЫМ источником критериев. Проверяй ровно то, что в нём написано. Не добавляй требований, которых в условии нет. Разрешай синонимы и переформулировки по смыслу.
3. Условия могут быть любой сложности (и/или/не, пороги, диапазоны, упоминания конкретных слов/фраз и т.п.) — трактуй их буквально и по смыслу:
   * «и/все/обязательно» → конъюнкция (AND)
   * «или/любой из/достаточно» → дизъюнкция (OR)
   * «необязательно/по возможности» → не является требованием
4. Если в условии НЕ задана строгая конкретика (число, дата, формат) — засчитывай смысловые эквиваленты, практическую достаточность.
5. Если строгость указана — проверяй строго.
6. Оценивай весь диалог целиком. Если критерии частично выполнены — вердикт FAIL и перечисли недостающее.
7. При двусмысленности трактуй минимально необходимое, но не добавляй новые требования.
8. Ответ всегда строго в JSON-формате:

{
  "verdict": true|false,
  "missing": ["..."] | [],
  "notes": "краткое пояснение (1–2 предложения)"
}

На вход ты всегда получаешь JSON:

{
  "condition": "...",
  "dialog": "..."
}
EOT;
        $payload = [
            'model' => $this->setting->model ?? 'gpt-4.1',
            'input' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => json_encode($input, JSON_UNESCAPED_UNICODE),
                ],
            ],
            'text' => [
                'format' => [
                    'type'      => 'json_schema',
                    'name'      => $jsonSchema['name'],
                    'schema'    => $jsonSchema['schema'],
                ],
            ],
        ];

        try {
            $response = $this->getRequestBase()->post('/responses', $payload)->json();
            $content = $response['output'][0]['content'][0]['text'] ?? '{}';

            $usage = $response['usage'] ?? [];
            $this->storeUsage($usage, $payload['model'], $this->setting->account_id);

            Log::channel('amocrm')->info("\$response - " . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $parsed = json_decode($content, true);
            if (!is_array($parsed)) {
                Log::channel('amocrm')->warning('Arbitration: invalid JSON output', ['text' => $content]);
                return [false, ['invalid_output'], 'Результат арбитра не является корректным JSON.'];
            }

            return [
                (bool)($parsed['verdict'] ?? false),
                is_array($parsed['missing'] ?? null) ? $parsed['missing'] : [],
                (string)($parsed['notes'] ?? ''),
            ];
        } catch (\Throwable $e) {
            Log::channel('amocrm')->error("OpenAIArbitrationService error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return [false, ['exception'], 'Ошибка обращения к арбитру: ' . $e->getMessage()];
        }
    }
}
