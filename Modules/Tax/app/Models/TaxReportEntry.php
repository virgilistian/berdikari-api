<?php

namespace Modules\Tax\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxReportEntry extends Model
{
    use HasUuids;

    protected $fillable = [
        'tax_report_id',
        'day_number',
        'weekday_name',
        'is_weekend',
        'is_holiday',
        'holiday_name',
        'ticket_qty',
        'ticket_price',
        'extra',
        'sales',
        'tax',
        'is_manually_edited',
    ];

    protected $casts = [
        'is_weekend'          => 'boolean',
        'is_holiday'          => 'boolean',
        'ticket_qty'          => 'integer',
        'ticket_price'        => 'decimal:2',
        'extra'               => 'array',
        'sales'               => 'decimal:2',
        'tax'                 => 'decimal:2',
        'is_manually_edited'  => 'boolean',
    ];

    public function taxReport(): BelongsTo
    {
        return $this->belongsTo(TaxReport::class);
    }
}
