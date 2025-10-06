<?php

namespace App\Models\AiLead\Pipeline;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $lead_id
 * @property integer $status_id
 * @property integer $pipeline_id
 *
 * @mixin Builder
 */
class LeadPipelineStatus extends Model
{
    protected $table = 'lead_pipeline_status';
    protected $primaryKey = 'lead_id';

    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'lead_id',
        'status_id',
        'pipeline_id',
    ];

    protected $casts = [
        'lead_id' => 'integer',
        'status_id' => 'integer',
        'pipeline_id' => 'integer',
    ];

    public function pipeline()
    {
        return $this->belongsTo(Pipeline::class, 'pipeline_id');
    }

    public function status()
    {
        return $this->belongsTo(PipelineStatus::class, 'status_id');
    }
}
