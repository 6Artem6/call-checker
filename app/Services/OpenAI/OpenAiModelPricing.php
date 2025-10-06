<?php

namespace App\Services\OpenAI;

use Illuminate\Database\Eloquent\Model;

class OpenAIModelPricing extends Model
{
    protected $table = 'openai_model_pricing';

    protected $fillable = [
        'model', 'input', 'cached', 'output',
    ];
}
