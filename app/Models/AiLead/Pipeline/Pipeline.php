<?php

namespace App\Models\AiLead\Pipeline;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property integer $account_id
 * @property string $name
 * @property integer $sort
 * @property boolean $is_main
 * @property boolean $is_unsorted_on
 * @property boolean $is_archive
 *
 * @mixin Builder
 */
class Pipeline extends Model
{
    protected $table = 'pipeline';
    protected $primaryKey = 'id';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'account_id',
        'name',
        'sort',
        'is_main',
        'is_unsorted_on',
        'is_archive',
    ];

    protected $casts = [
        'id' => 'integer',
        'account_id' => 'integer',
        'name' => 'string',
        'sort' => 'integer',
        'is_main' => 'boolean',
        'is_unsorted_on' => 'boolean',
        'is_archive' => 'boolean',
    ];

    public function statuses()
    {
        return $this->hasMany(PipelineStatus::class, 'pipeline_id');
    }

    public function leadStatuses()
    {
        return $this->hasMany(LeadPipelineStatus::class, 'pipeline_id');
    }
}
