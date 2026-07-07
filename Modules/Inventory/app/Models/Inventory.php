<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Models\Product;
use Modules\Core\Traits\Tenantable;

class Inventory extends Model
{
    use HasFactory, HasUuids, Tenantable;

    protected $fillable = ['business_id', 'branch_id', 'product_id', 'quantity', 'min_stock'];

    protected $casts = [
        'quantity'  => 'integer',
        'min_stock' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
