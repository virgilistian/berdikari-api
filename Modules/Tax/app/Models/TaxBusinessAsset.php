<?php

namespace Modules\Tax\Models;

use App\Models\Business;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Traits\Tenantable;

class TaxBusinessAsset extends Model
{
    use HasUuids, Tenantable;

    protected $fillable = [
        'business_id',
        'type',
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'width',
        'height',
    ];

    protected $appends = ['data_uri'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Base64 data URI so dompdf (and the settings preview UI) can embed the
     * (possibly private/signed-only) asset without a network round trip.
     */
    public function toDataUri(): ?string
    {
        if (! Storage::disk($this->disk)->exists($this->path)) {
            return null;
        }

        $contents = Storage::disk($this->disk)->get($this->path);

        return 'data:' . $this->mime_type . ';base64,' . base64_encode($contents);
    }

    public function getDataUriAttribute(): ?string
    {
        return $this->toDataUri();
    }
}
