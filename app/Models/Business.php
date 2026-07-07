<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Business extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'tax_id'];
}
