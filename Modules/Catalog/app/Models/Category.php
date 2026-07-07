<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Traits\Tenantable;

class Category extends Model
{
    use HasFactory, HasUuids, Tenantable;

    protected $fillable = ['business_id', 'name'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
