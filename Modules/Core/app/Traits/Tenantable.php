<?php

namespace Modules\Core\Traits;

use Modules\Core\Scopes\TenantScope;

trait Tenantable
{
    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (auth()->check() && auth()->user()->business_id && empty($model->business_id)) {
                $model->business_id = auth()->user()->business_id;
            }
        });
    }
}
