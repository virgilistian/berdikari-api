<?php

namespace Modules\Finance\Models;

use App\Models\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Traits\Tenantable;

class FinanceEntry extends Model
{
    use HasFactory, HasUuids, Tenantable;

    protected $fillable = [
        'business_id',
        'type',
        'amount',
        'category',
        'note',
        'source_type',
        'source_id',
        'occurred_at',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'occurred_at' => 'datetime',
    ];

    protected $appends = ['business_name'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function getBusinessNameAttribute(): ?string
    {
        return $this->business?->name;
    }

    public function scopeIncome($query)
    {
        return $query->where('type', 'income');
    }

    public function scopeExpense($query)
    {
        return $query->where('type', 'expense');
    }
}
