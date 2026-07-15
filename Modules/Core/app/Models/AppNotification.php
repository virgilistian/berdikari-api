<?php

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Traits\Tenantable;

class AppNotification extends Model
{
    use HasFactory, HasUuids, Tenantable;

    protected $table = 'app_notifications';

    protected $fillable = [
        'business_id',
        'user_id',
        'role_target',
        'type',
        'title',
        'body',
        'meta',
        'read_at',
    ];

    protected $casts = [
        'meta'    => 'array',
        'read_at' => 'datetime',
    ];

    protected $appends = ['is_read'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getIsReadAttribute(): bool
    {
        return $this->read_at !== null;
    }
}
