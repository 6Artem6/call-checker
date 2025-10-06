<?php

namespace App\Models\AiLead\Gpt;

use App\Models\AiLead\Account\AccountOAuth2;
use App\Models\AiLead\Pipeline\PipelineStatus;
use Illuminate\Database\Eloquent\Model;

class AccountGPTSetting extends Model
{
    protected $table = 'account_gpt_settings';
    public $timestamps = false;
    protected $primaryKey = 'pipeline_status_id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'account_id',
        'setting_id',
        'pipeline_status_id',
    ];

    protected $casts = [
        'account_id' => 'integer',
        'setting_id' => 'integer',
        'pipeline_status_id' => 'integer',
    ];

    public function files()
    {
        return $this->hasMany(ChatGPTFile::class, 'setting_id', 'setting_id');
    }

    public function account()
    {
        return $this->belongsTo(AccountOAuth2::class, 'account_id', 'account_id');
    }

    public function setting()
    {
        return $this->belongsTo(ChatGPTSetting::class, 'setting_id', 'setting_id');
    }

    public function pipelineStatus()
    {
        return $this->belongsTo(PipelineStatus::class, 'pipeline_status_id');
    }
}
