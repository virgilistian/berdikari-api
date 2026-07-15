<?php

namespace Modules\Sales\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Traits\Tenantable;

class CashierShift extends Model
{
    use HasFactory, HasUuids, Tenantable;

    protected $fillable = [
        'business_id',
        'user_id',
        'status',
        'opening_cash',
        'closing_cash',
        'expected_cash',
        'cash_difference',
        'transaction_count',
        'total_sales',
        'payment_breakdown',
        'closing_note',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'opening_cash'      => 'decimal:2',
        'closing_cash'      => 'decimal:2',
        'expected_cash'     => 'decimal:2',
        'cash_difference'   => 'decimal:2',
        'total_sales'       => 'decimal:2',
        'payment_breakdown' => 'array',
        'opened_at'         => 'datetime',
        'closed_at'         => 'datetime',
    ];

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(SaleOrder::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
