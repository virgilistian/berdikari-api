<?php

namespace Modules\Tax\Models;

use App\Models\Business;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Traits\Tenantable;

class TaxReport extends Model
{
    use HasUuids, Tenantable;

    protected $fillable = [
        'business_id',
        'business_type',
        'period_month',
        'period_year',
        'status',
        'holiday_count_in_month',
        'monthly_cap',
        'total_sales',
        'total_tax',
        'was_normalized',
        'config_snapshot',
        'generated_by',
        'generated_at',
        'last_printed_at',
        'print_count',
    ];

    protected $casts = [
        'monthly_cap'      => 'decimal:2',
        'total_sales'      => 'decimal:2',
        'total_tax'        => 'decimal:2',
        'was_normalized'   => 'boolean',
        'config_snapshot'  => 'array',
        'generated_at'     => 'datetime',
        'last_printed_at'  => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TaxReportEntry::class)->orderBy('day_number');
    }

    public function scopeForPeriod($query, int $month, int $year)
    {
        return $query->where('period_month', $month)->where('period_year', $year);
    }
}
