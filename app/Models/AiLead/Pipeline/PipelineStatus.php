<?php

namespace App\Models\AiLead\Pipeline;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property integer $pipeline_id
 * @property string $name
 * @property integer $sort
 * @property integer $type
 *
 * @mixin Builder
 */
class PipelineStatus extends Model
{
    protected $table = 'pipeline_status';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'pipeline_id',
        'name',
        'sort',
        'type',
    ];

    protected $hidden = [
        'id',
    ];

    protected $casts = [
        'id' => 'integer',
        'pipeline_id' => 'integer',
        'name' => 'string',
        'sort' => 'integer',
        'type' => 'integer',
    ];

    public function pipeline()
    {
        return $this->belongsTo(Pipeline::class, 'pipeline_id');
    }

    public function leadStatuses()
    {
        return $this->hasMany(LeadPipelineStatus::class, 'status_id');
    }
}
