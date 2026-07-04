<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DailyStock extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'business_id', 'date', 'product_id', 'product_name',
        'opening_qty', 'sold_qty', 'closing_qty', 'status',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];
}
