<?php

namespace Modules\Inventory\Listeners;

use Modules\Inventory\Services\InventoryService;
use Modules\Sales\Events\SaleOrderCompleted;

class DeductInventoryOnSale
{
    public function __construct(private InventoryService $service) {}

    public function handle(SaleOrderCompleted $event): void
    {
        $this->service->deductForSale($event->order);
    }
}
