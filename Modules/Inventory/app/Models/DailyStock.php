<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Modules\Core\Traits\Tenantable;

class DailyStock extends Model
{
    use HasFactory, HasUuids, Tenantable;

    protected $fillable = [
        'business_id', 'date', 'product_id', 'product_name',
        'price', 'image_url',
        'opening_qty', 'adjustment_qty', 'adjustment_note',
        'sold_qty', 'closing_qty', 'status',
    ];

    protected $casts = [
        'date'           => 'date:Y-m-d',
        'price'          => 'decimal:2',
        'opening_qty'    => 'integer',
        'adjustment_qty' => 'integer',
        'sold_qty'       => 'integer',
        'closing_qty'    => 'integer',
    ];
}
