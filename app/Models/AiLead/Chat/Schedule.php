<?php

namespace App\Models\AiLead\Chat;

use App\Models\AiLead\Gpt\ChatGPTSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Schedule extends Model
{

    protected $table = 'schedules';
    protected $primaryKey = 'id';
    protected $keyType = 'int';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'setting_id',
        'weekday',
        'time_from',
        'time_to',
    ];

    protected $casts = [
//        'time_from' => 'string',
//        'time_to' => 'string',
    ];

    public function getTimeFromAttribute($value): ?string
    {
        return $value ? Carbon::parse($value)->format('H:i') : null;
    }

    public function getTimeToAttribute($value): ?string
    {
        return $value ? Carbon::parse($value)->format('H:i') : null;
    }

    public function setting()
    {
        return $this->belongsTo(ChatGPTSetting::class);
    }
    
    public static function isActiveNow(int $settingId, ?Carbon $now = null): bool
    {
        $now = $now ?? Carbon::now();
        $weekday = $now->dayOfWeekIso; // 1–7
        $time = $now->format('H:i');

        // Если нет расписания — бот всегда активен
        if (!self::where('setting_id', $settingId)->exists()) {
            return true;
        }

        // Проверяем наличие интервала для текущего дня
        return self::query()
            ->where('setting_id', $settingId)
            ->where('weekday', $weekday)
            ->where('time_from', '<=', $time)
            ->where('time_to', '>=', $time)
            ->exists();
    }
}
