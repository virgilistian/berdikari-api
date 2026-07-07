<?php

namespace Modules\Inventory\Listeners;

use Modules\Inventory\Services\InventoryService;
use Modules\Sales\Events\SaleOrderRefunded;

class RestoreInventoryOnRefund
{
    public function __construct(private InventoryService $service) {}

    public function handle(SaleOrderRefunded $event): void
    {
        $this->service->restoreForRefund($event->order);
    }
}
