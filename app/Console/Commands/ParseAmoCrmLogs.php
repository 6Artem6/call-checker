<?php

namespace App\Console\Commands;

use App\Models\AiLead\Pipeline\{Pipeline, PipelineStatus, LeadPipelineStatus};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;


class ParseAmoCrmLogs extends Command
{
    protected $signature = 'parse:amocrm-logs {path : Путь к файлу лога}';
    protected $description = 'Разбор AmoCRM Webhook LeadStatus из логов и запись в БД';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (!file_exists($path)) {
            $this->error("Файл {$path} не найден");
            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            $this->error("Не удалось открыть файл {$path}");
            return self::FAILURE;
        }

        $count = 0;
        while (($line = fgets($handle)) !== false) {
            if (!str_contains($line, 'AmoCRM Webhook LeadStatus:')) {
                continue;
            }

            // Вырезаем JSON
            $pos = strpos($line, '{');
            if ($pos === false) {
                continue;
            }
            $json = substr($line, $pos);

            $data = json_decode($json, true, 512, JSON_INVALID_UTF8_IGNORE);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Не удалось декодировать JSON', [
                    'error' => json_last_error_msg(),
                    'line'  => mb_substr($line, 0, 500), // кусочек строки для дебага
                ]);
                unset($json, $line, $data);
                gc_collect_cycles();
                continue;
            }

            foreach ($data['leads']['update'] ?? [] as $lead) {
                if (
                    !is_numeric($lead['id'] ?? null) ||
                    !is_numeric($lead['pipeline_id'] ?? null) ||
                    !is_numeric($lead['status_id'] ?? null)
                ) {
                    continue;
                }

                $leadId = (int) $lead['id'];
                $statusId = (int) $lead['status_id'];
                $pipelineId = (int) $lead['pipeline_id'];

                PipelineStatus::firstOrCreate(
                    ['id' => $statusId],
                    [
                        'pipeline_id' => $pipelineId,
                        'name' => 'Unknown',
                    ]
                );

                LeadPipelineStatus::updateOrCreate(
                    ['lead_id' => $leadId],
                    [
                        'status_id' => $statusId,
                        'pipeline_id' => $pipelineId,
                    ]
                );

                $count++;
            }

            // освобождаем память
            unset($json, $data, $lead, $line);
            if ($count % 500 === 0) {
                gc_collect_cycles();
            }
        }

        fclose($handle);
        $this->info("Обработано {$count} лидов из логов");

        return self::SUCCESS;
    }
}
