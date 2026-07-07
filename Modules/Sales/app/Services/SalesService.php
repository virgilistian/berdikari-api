<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Facades\DB;
use Modules\Sales\Events\SaleOrderCompleted;
use Modules\Sales\Events\SaleOrderRefunded;
use Modules\Sales\Events\SalePaymentReceived;
use Modules\Sales\Models\SaleOrder;
use Modules\Sales\Models\SaleOrderItem;
use Modules\Sales\Models\SalePayment;

class SalesService
{
    /**
     * Create a sale order.
     *
     * @param  array{
     *   items: array<int, array{product_id:string, quantity:int, unit_price:float}>,
     *   action?: string, customer_name?: string|null, note?: string|null,
     *   payments?: array<int, array{amount:float, method?:string, note?:string|null}>
     * }  $data
     */
    public function createOrder(string $businessId, ?string $userId, array $data): SaleOrder
    {
        $action = $data['action'] ?? 'complete'; // hold | complete

        return DB::transaction(function () use ($businessId, $userId, $data, $action) {
            $total = collect($data['items'])->sum(fn ($i) => $i['quantity'] * $i['unit_price']);

            $order = SaleOrder::create([
                'business_id'    => $businessId,
                'order_no'       => $this->generateOrderNo($businessId),
                'user_id'        => $userId,
                'status'         => $action === 'hold' ? 'open' : 'completed',
                'payment_status' => 'unpaid',
                'total_amount'   => $total,
                'paid_amount'    => 0,
                'change_amount'  => 0,
                'customer_name'  => $data['customer_name'] ?? null,
                'note'           => $data['note'] ?? null,
                'completed_at'   => $action === 'hold' ? null : now(),
            ]);

            foreach ($data['items'] as $item) {
                SaleOrderItem::create([
                    'sale_order_id' => $order->id,
                    'product_id'    => $item['product_id'],
                    'quantity'      => $item['quantity'],
                    'unit_price'    => $item['unit_price'],
                    'subtotal'      => $item['quantity'] * $item['unit_price'],
                ]);
            }

            // Finalized orders deduct stock immediately (goods handed over).
            if ($action !== 'hold') {
                event(new SaleOrderCompleted($order));
            }

            // Apply any payments supplied at creation (full, partial or none).
            foreach ($data['payments'] ?? [] as $payment) {
                if (($payment['amount'] ?? 0) > 0) {
                    $this->addPayment($order, (float) $payment['amount'], $payment['method'] ?? 'cash', $payment['note'] ?? null);
                }
            }

            return $order->fresh(['items', 'payments']);
        });
    }

    /**
     * Finalize a previously held/suspended order: deduct stock and optionally pay.
     *
     * @param  array<int, array{amount:float, method?:string, note?:string|null}>  $payments
     */
    public function completeOrder(SaleOrder $order, array $payments = []): SaleOrder
    {
        if ($order->status !== 'open') {
            abort(422, 'Hanya pesanan tersimpan yang dapat diselesaikan.');
        }

        return DB::transaction(function () use ($order, $payments) {
            $order->update(['status' => 'completed', 'completed_at' => now()]);

            event(new SaleOrderCompleted($order));

            foreach ($payments as $payment) {
                if (($payment['amount'] ?? 0) > 0) {
                    $this->addPayment($order, (float) $payment['amount'], $payment['method'] ?? 'cash', $payment['note'] ?? null);
                }
            }

            return $order->fresh(['items', 'payments']);
        });
    }

    /**
     * Record a payment against an order (settles pay-later / partial balances).
     * `amount` is treated as cash tendered; only the outstanding balance is
     * applied to the order, the remainder becomes change.
     */
    public function addPayment(SaleOrder $order, float $amount, string $method = 'cash', ?string $note = null): SalePayment
    {
        return DB::transaction(function () use ($order, $amount, $method, $note) {
            $balanceDue = max(0, (float) $order->total_amount - (float) $order->paid_amount);
            $applied    = min($amount, $balanceDue);
            $change     = round($amount - $applied, 2);

            $payment = SalePayment::create([
                'business_id'   => $order->business_id,
                'sale_order_id' => $order->id,
                'amount'        => $applied,
                'method'        => $method,
                'note'          => $note,
                'paid_at'       => now(),
            ]);

            $paid = round((float) $order->paid_amount + $applied, 2);
            $order->update([
                'paid_amount'    => $paid,
                'change_amount'  => round((float) $order->change_amount + $change, 2),
                'payment_status' => $this->resolvePaymentStatus($paid, (float) $order->total_amount),
            ]);

            // Cash-basis income recognition.
            if ($applied > 0) {
                event(new SalePaymentReceived($order->fresh(), $payment));
            }

            return $payment;
        });
    }

    /**
     * Cancel a saved/suspended order (no stock or finance side-effects).
     */
    public function cancelOrder(SaleOrder $order): SaleOrder
    {
        if ($order->status !== 'open') {
            abort(422, 'Hanya pesanan tersimpan yang dapat dibatalkan.');
        }

        $order->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        return $order->fresh(['items', 'payments']);
    }

    /**
     * Refund a completed order: restore stock and reverse recognised income.
     */
    public function refundOrder(SaleOrder $order): SaleOrder
    {
        if ($order->status !== 'completed') {
            abort(422, 'Hanya pesanan selesai yang dapat direfund.');
        }

        return DB::transaction(function () use ($order) {
            $refundAmount = (float) $order->paid_amount;

            $order->update([
                'status'         => 'refunded',
                'payment_status' => 'refunded',
                'refunded_at'    => now(),
            ]);

            event(new SaleOrderRefunded($order, $refundAmount));

            return $order->fresh(['items', 'payments']);
        });
    }

    /**
     * List orders with optional filters.
     */
    public function listOrders(string $businessId, array $filters = [])
    {
        $query = SaleOrder::with(['items', 'payments'])
            ->where('business_id', $businessId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (! empty($filters['date'])) {
            $query->whereDate('created_at', $filters['date']);
        }

        return $query->orderByDesc('created_at')->limit(100)->get();
    }

    private function resolvePaymentStatus(float $paid, float $total): string
    {
        if ($paid <= 0) {
            return 'unpaid';
        }

        return $paid >= $total ? 'paid' : 'partial';
    }

    private function generateOrderNo(string $businessId): string
    {
        $seq = SaleOrder::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->whereDate('created_at', now()->toDateString())
            ->count() + 1;

        return 'NOTA-' . now()->format('ymd') . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
