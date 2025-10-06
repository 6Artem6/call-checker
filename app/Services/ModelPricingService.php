<?php

namespace App\Services;


use App\Services\OpenAI\OpenAIModelPricing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ModelPricingService
{
    public function getAllPricing(): array
    {
        return OpenAIModelPricing::all()
            ->keyBy('model')
            ->map(fn ($m) => [
                'input'  => $m->input,
                'cached' => $m->cached,
                'output' => $m->output,
            ])
            ->toArray();
    }

    public function getPricingForModel(string $modelName): ?array
    {
        $key = strtolower($modelName);

        $pricing = OpenAIModelPricing::where('model', $key)->first();
        if ($pricing) {
            return [
                'input'  => $pricing->input,
                'cached' => $pricing->cached,
                'output' => $pricing->output,
            ];
        }

        // fallback: частичное совпадение
        $pricing = OpenAIModelPricing::where('model', 'like', $key . '%')->first();
        if ($pricing) {
            return [
                'input'  => $pricing->input,
                'cached' => $pricing->cached,
                'output' => $pricing->output,
            ];
        }

        return null;
    }

    public function getUsdRubRate(): float
    {
        return Cache::remember('usd_rub_rate', 600, function () {
            try {
                $resp = file_get_contents('https://www.cbr-xml-daily.ru/daily_json.js');
                $json = json_decode($resp, true);
                return $json['Valute']['USD']['Value'] ?? 100.0;
            } catch (\Throwable $e) {
                Log::channel('amocrm')->warning('Не удалось получить курс USD: ' . $e->getMessage());
                return 100.0;
            }
        });
    }
}
