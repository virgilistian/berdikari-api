<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class InventoryMovement extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['inventory_id', 'type', 'quantity', 'reason'];
}
