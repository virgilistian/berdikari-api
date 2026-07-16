<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Business extends Model
{
    use HasUuids;

    protected $fillable = [
        'name', 'tax_id', 'type', 'code', 'address', 'phone',
        'logo_disk', 'logo_path', 'status',
    ];

    protected $appends = ['logo_data_uri'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_user')->withTimestamps();
    }

    /**
     * Base64 data URI so the logo (stored on a private MinIO/S3 disk) can be
     * rendered directly without a network round trip — mirrors
     * Modules\Tax\Models\TaxBusinessAsset::getDataUriAttribute().
     */
    public function getLogoDataUriAttribute(): ?string
    {
        if (! $this->logo_path || ! $this->logo_disk) {
            return null;
        }

        if (! Storage::disk($this->logo_disk)->exists($this->logo_path)) {
            return null;
        }

        $extension = strtolower(pathinfo($this->logo_path, PATHINFO_EXTENSION));
        $mime = $extension === 'png' ? 'image/png' : 'image/jpeg';
        $contents = Storage::disk($this->logo_disk)->get($this->logo_path);

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }
}
