<?php

namespace Modules\Tax\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class TaxHoliday extends Model
{
    use HasUuids;

    public const TYPE_NATIONAL = 'national';

    public const TYPE_EID_AL_FITR = 'eid_al_fitr';

    protected $fillable = [
        'date',
        'name',
        'type',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * Bust DatabaseHolidayProvider's per-year cache whenever a holiday
     * changes, so edits take effect immediately rather than waiting out the
     * cache TTL — "admin-editable without a deploy" should mean promptly
     * effective, not effective-in-up-to-6-hours.
     */
    protected static function booted(): void
    {
        static::saved(function (self $holiday) {
            Cache::forget("tax:holidays:{$holiday->date->year}");
            Cache::forget("tax:holidays:eid:{$holiday->date->year}");
        });
        static::deleted(function (self $holiday) {
            Cache::forget("tax:holidays:{$holiday->date->year}");
            Cache::forget("tax:holidays:eid:{$holiday->date->year}");
        });
    }
}
