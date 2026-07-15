<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Modules\Core\Traits\Tenantable;

class FinanceCategory extends Model
{
    use HasFactory, HasUuids, Tenantable;

    protected $fillable = ['business_id', 'name', 'type'];

    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
