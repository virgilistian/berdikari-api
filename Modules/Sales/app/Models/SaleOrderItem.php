<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SaleOrderItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'sale_order_id',
        'product_id',
        'quantity',
        'unit_price',
        'subtotal'
    ];
}
