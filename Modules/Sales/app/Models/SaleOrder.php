<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Modules\Core\Traits\Tenantable;

class SaleOrder extends Model
{
    use HasFactory, HasUuids, Tenantable;

    protected $fillable = [
        'business_id',
        'user_id',
        'status',
        'total_amount'
    ];

    public function items()
    {
        return $this->hasMany(SaleOrderItem::class);
    }
}
