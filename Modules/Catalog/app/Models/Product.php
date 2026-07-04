<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Modules\Core\Traits\Tenantable;

class Product extends Model
{
    use HasFactory, HasUuids, Tenantable;

    protected $fillable = ['business_id', 'category_id', 'name', 'sku', 'price', 'description', 'image_url'];
}
