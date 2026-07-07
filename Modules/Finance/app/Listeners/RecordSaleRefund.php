<?php

namespace Modules\Finance\Listeners;

use Modules\Finance\Models\FinanceEntry;
use Modules\Sales\Events\SaleOrderRefunded;

/**
 * Records a refund as an expense in the Finance ledger, reversing the
 * income previously recognised for the order.
 */
class RecordSaleRefund
{
    public function handle(SaleOrderRefunded $event): void
    {
        if ($event->refundAmount <= 0) {
            return;
        }

        $order = $event->order;

        FinanceEntry::create([
            'business_id' => $order->business_id,
            'type'        => 'expense',
            'amount'      => $event->refundAmount,
            'category'    => 'Refund Penjualan',
            'note'        => 'Refund pesanan ' . ($order->order_no ?? $order->id),
            'source_type' => 'sale_order_refund',
            'source_id'   => $order->id,
            'occurred_at' => now(),
        ]);
    }
}
