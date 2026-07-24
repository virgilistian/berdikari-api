<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Traits\Tenantable;

class Product extends Model
{
    use HasFactory, HasUuids, Tenantable;

    protected $fillable = [
        'business_id',
        'category_id',
        'name',
        'sku',
        'price',
        'purchase_price',
        'cost_price',
        'is_active',
        'description',
        'image_url',
        // Only ProductController's upload/delete actions ever pass these —
        // the generic store/update validation rules never include them, so
        // a client can't set them through the regular create/update body.
        'photo_disk',
        'photo_path',
        'photo_mime_type',
    ];

    protected $casts = [
        'price'          => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'cost_price'     => 'decimal:2',
        'is_active'      => 'boolean',
    ];

    protected $appends = ['has_photo'];

    protected $hidden = ['photo_disk', 'photo_path', 'photo_mime_type'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function getHasPhotoAttribute(): bool
    {
        return ! empty($this->photo_path);
    }
}
