<?php

namespace Modules\Inventory\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        \Modules\Sales\Events\SaleOrderCompleted::class => [
            \Modules\Inventory\Listeners\DeductDailyStockOnSale::class,
            \Modules\Inventory\Listeners\DeductInventoryOnSale::class,
        ],
        \Modules\Sales\Events\SaleOrderRefunded::class => [
            \Modules\Inventory\Listeners\RestoreInventoryOnRefund::class,
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = false;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
