<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'business_id',
        'inventory_id',
        'product_id',
        'type',
        'quantity',
        'unit_cost',
        'balance_after',
        'reason',
        'reference_type',
        'reference_id',
    ];

    protected $casts = [
        'quantity'      => 'integer',
        'unit_cost'     => 'decimal:2',
        'balance_after' => 'integer',
    ];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }
}
