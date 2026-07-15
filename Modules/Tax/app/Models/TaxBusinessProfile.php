<?php

namespace Modules\Tax\Models;

use App\Models\Business;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Traits\Tenantable;

class TaxBusinessProfile extends Model
{
    use HasUuids, Tenantable;

    protected $fillable = [
        'business_id',
        'business_type',
        'npwpd',
        'company_name',
        'company_address',
        'owner_name',
        'config_overrides',
    ];

    protected $casts = [
        'config_overrides' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
