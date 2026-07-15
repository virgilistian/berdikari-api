<?php

namespace Modules\HR\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Traits\Tenantable;

class LeaveQuota extends Model
{
    use HasFactory, HasUuids, Tenantable;

    protected $fillable = [
        'business_id',
        'employee_id',
        'year',
        'type',
        'quota_days',
        'used_days',
        'pending_days',
        'carryover_days',
        'expires_at',
    ];

    protected $casts = [
        'year'           => 'integer',
        'quota_days'     => 'integer',
        'used_days'      => 'integer',
        'pending_days'   => 'integer',
        'carryover_days' => 'integer',
        'expires_at'     => 'datetime',
    ];

    protected $appends = ['remaining_days', 'total_available'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Remaining days = quota + carryover - used - pending */
    public function getRemainingDaysAttribute(): int
    {
        return max(0, $this->quota_days + $this->carryover_days - $this->used_days - $this->pending_days);
    }

    /** Total available = quota + carryover */
    public function getTotalAvailableAttribute(): int
    {
        return $this->quota_days + $this->carryover_days;
    }
}
