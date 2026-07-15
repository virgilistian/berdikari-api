<?php

namespace Modules\Tax\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class TaxHoliday extends Model
{
    use HasUuids;

    protected $fillable = [
        'date',
        'name',
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
        static::saved(fn (self $holiday) => Cache::forget("tax:holidays:{$holiday->date->year}"));
        static::deleted(fn (self $holiday) => Cache::forget("tax:holidays:{$holiday->date->year}"));
    }
}
