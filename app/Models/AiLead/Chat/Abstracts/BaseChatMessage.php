<?php

namespace App\Models\AiLead\Chat\Abstracts;

use App\Services\OpenAIAnalysisService;
use App\Services\SeleniumService;
use App\Services\PuppeteerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\AiLead\Account\AccountOAuth2;
use App\Models\AiLead\Gpt\ChatGPTSetting;


/**
 * @mixin Builder
 */
abstract class BaseChatMessage extends Model
{
    protected ?ChatGPTSetting $setting = null;

    public function setSetting(ChatGPTSetting $setting) {
        $this->setting = $setting;
    }

    public function formatJsonToArray($data) {
        // Проверяем, является ли $data JSON-строкой и декодируем её
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data = $decoded;
            }
        }
        return $data;
    }

    public function formatArrayToText($data) {
        if (is_string($data)) {
            return $data;
        }
        // Если нет ключа 'text', возвращаем данные как есть
        if (!isset($data['text'])) {
            return $data;
        }

        $text = $data['text'];
        return trim($text);
    }

    public function formatArrayDataToText($data): string
    {
        if (is_string($data)) {
            return $data;
        }

        $text = "";

        // Блок для стандартного массива data
        if (!empty($data['data']) && is_array($data['data'])) {
            $text .= "\n";
            foreach ($data['data'] as $item) {
                if (isset($item['name'])) {
                    $text .= "\n- " . $item['name'] . ": " . ($item['value'] ?? '');
                }
            }
        }

        // Блок для обработки списка товаров items
        if (!empty($data['items']) && is_array($data['items'])) {
            $text .= "\n\nТовары в заказе:";
            foreach ($data['items'] as $index => $item) {
                $text .= "\n\n#" . ($index + 1);
                $text .= "\nНазвание: " . ($item['name'] ?? '-');
                $text .= "\nОписание: " . ($item['description'] ?? '-');
                $text .= "\nКоличество: " . ($item['count'] ?? '-');
                $text .= "\nЦена за единицу: " . ($item['price_per_unit'] ?? '-');
                $text .= "\nОбщая стоимость: " . ($item['total_price'] ?? '-');
            }
        }

        return trim($text);
    }

    public function formatTasksToText($data): string
    {
        // Если строка — пробуем декодировать
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data = $decoded;
            } else {
                return $data; // Возвращаем как есть
            }
        }

        // Если есть ключ 'task' (единичная задача), оборачиваем в массив
        if (!empty($data['task']) && is_array($data['task'])) {
            $tasks = [$data['task']];
        } elseif (!empty($data['tasks']) && is_array($data['tasks'])) {
            $tasks = $data['tasks'];
        } else {
            return '';
        }

        // Форматируем каждую задачу
        $result = "Задачи для оператора:";
        foreach ($tasks as $i => $task) {
            $index = $i + 1;
            $type = $task['type'] ?? 'неизвестный тип';
            $description = $task['description'] ?? '-';
            $original = $task['original_question'] ?? '-';

            $result .= "\n\n{$index}. Тип: {$type}";
            $result .= "\nОписание: {$description}";
            $result .= "\nОригинальный вопрос: {$original}";
        }

        return trim($result);
    }

    public function formatArrayToQuestions($data): array
    {
        if (is_string($data)) {
            $data = $this->formatJsonToArray($data);
        }

        $questions = [];

        // Если это обычный массив с 'text'
        if (!empty($data['text'])) {
            $questions[] = trim($data['text']);
        }

        // Если есть вложенные данные 'data' (например, список вопросов)
        if (!empty($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $item) {
                if (!empty($item['text'])) {
                    $questions[] = trim($item['text']);
                }
            }
        }

        return $questions;
    }
}
