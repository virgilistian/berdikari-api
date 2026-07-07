<?php

namespace Modules\Sales\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Sales\Models\SaleOrder;

/**
 * Fired when a completed order is refunded. Inventory restores stock and
 * Finance records a refund expense for the amount previously paid.
 */
class SaleOrderRefunded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SaleOrder $order,
        public float $refundAmount,
    ) {}
}
