<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Traits\Tenantable;

class Product extends Model
{
    use HasFactory, HasUuids, Tenantable;

    protected $fillable = [
        'business_id',
        'category_id',
        'name',
        'sku',
        'price',
        'purchase_price',
        'cost_price',
        'is_active',
        'description',
        'image_url',
    ];

    protected $casts = [
        'price'          => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'cost_price'     => 'decimal:2',
        'is_active'      => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
