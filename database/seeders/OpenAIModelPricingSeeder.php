<?php

namespace Database\Seeders;

use App\Services\OpenAI\OpenAIModelPricing;
use Illuminate\Database\Seeder;

class OpenAIModelPricingSeeder extends Seeder
{
    public function run(): void
    {
        $path = storage_path('app/openai_pricing.json');
        $data = json_decode(file_get_contents($path), true);

        foreach ($data as $model => $row) {
            OpenAIModelPricing::updateOrCreate(
                ['model' => strtolower($model)],
                [
                    'input'  => $row['input'] ?? null,
                    'cached' => $row['cached_input'] ?? null,
                    'output' => $row['output'] ?? null,
                ]
            );
        }
    }
}
