<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Modules\Core\Traits\Tenantable;

class Category extends Model
{
    use HasFactory, HasUuids, Tenantable;

    protected $fillable = ['business_id', 'name'];
}
