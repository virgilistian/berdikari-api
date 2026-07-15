<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Traits\Tenantable;

class SaleOrder extends Model
{
    use HasFactory, HasUuids, Tenantable;

    protected $fillable = [
        'business_id',
        'order_no',
        'client_uuid',
        'cashier_shift_id',
        'user_id',
        'status',
        'payment_status',
        'total_amount',
        'paid_amount',
        'change_amount',
        'customer_name',
        'note',
        'completed_at',
        'cancelled_at',
        'refunded_at',
    ];

    protected $casts = [
        'total_amount'  => 'decimal:2',
        'paid_amount'   => 'decimal:2',
        'change_amount' => 'decimal:2',
        'completed_at'  => 'datetime',
        'cancelled_at'  => 'datetime',
        'refunded_at'   => 'datetime',
    ];

    protected $appends = ['balance_due'];

    public function items(): HasMany
    {
        return $this->hasMany(SaleOrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'cashier_shift_id');
    }

    /**
     * Outstanding amount still owed on this order (for pay-later / partial).
     */
    public function getBalanceDueAttribute(): float
    {
        return max(0, (float) $this->total_amount - (float) $this->paid_amount);
    }
}
