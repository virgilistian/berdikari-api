<?php

namespace Modules\Sales\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Sales\Models\SaleOrder;
use Modules\Sales\Models\SalePayment;

/**
 * Fired whenever a payment is received for a sale order (full or partial).
 * The Finance module listens to record income on a cash basis.
 */
class SalePaymentReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SaleOrder $order,
        public SalePayment $payment,
    ) {}
}
