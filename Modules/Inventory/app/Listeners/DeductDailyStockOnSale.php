<?php

namespace Modules\Inventory\Listeners;

use Modules\Inventory\Services\DailyStockService;
use Modules\Sales\Events\SaleOrderCompleted;

class DeductDailyStockOnSale
{
    public function __construct(private DailyStockService $service) {}

    public function handle(SaleOrderCompleted $event): void
    {
        $this->service->recordSale($event->order);
    }
}
