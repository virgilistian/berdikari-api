<?php

namespace Modules\Finance\Listeners;

use Modules\Finance\Models\FinanceEntry;
use Modules\Sales\Events\SalePaymentReceived;

/**
 * Records POS payments as income in the Finance ledger (cash basis).
 */
class RecordSaleIncome
{
    public function handle(SalePaymentReceived $event): void
    {
        $order   = $event->order;
        $payment = $event->payment;

        if ((float) $payment->amount <= 0) {
            return;
        }

        FinanceEntry::create([
            'business_id' => $order->business_id,
            'type'        => 'income',
            'amount'      => $payment->amount,
            'category'    => 'Penjualan',
            'note'        => 'Pembayaran pesanan ' . ($order->order_no ?? $order->id),
            'source_type' => 'sale_order',
            'source_id'   => $order->id,
            'occurred_at' => $payment->paid_at ?? now(),
        ]);
    }
}
