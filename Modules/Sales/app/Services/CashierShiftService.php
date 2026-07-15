<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Models\CashierShift;
use Modules\Sales\Models\SaleOrder;
use Modules\Sales\Models\SalePayment;

class CashierShiftService
{
    /**
     * Get the currently active shift for a user in a business.
     */
    public function activeShift(string $businessId, string $userId): ?CashierShift
    {
        return CashierShift::where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
    }

    /**
     * Get any active shift for the business (any cashier), used for validation.
     */
    public function anyActiveShift(string $businessId): ?CashierShift
    {
        return CashierShift::where('business_id', $businessId)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
    }

    /**
     * Open a new shift for a cashier.
     * Enforces: only one active shift per cashier at a time.
     *
     * @param  array{opening_cash: float}  $data
     */
    public function open(string $businessId, string $userId, array $data): CashierShift
    {
        $existing = $this->activeShift($businessId, $userId);
        abort_if($existing !== null, 422, 'Anda masih memiliki shift yang sedang aktif. Tutup shift terlebih dahulu.');

        return CashierShift::create([
            'business_id'  => $businessId,
            'user_id'      => $userId,
            'status'       => 'open',
            'opening_cash' => $data['opening_cash'] ?? 0,
            'opened_at'    => now(),
        ]);
    }

    /**
     * Close the active shift with cash counting and summary calculation.
     *
     * @param  array{closing_cash: float, closing_note?: string|null}  $data
     */
    public function close(CashierShift $shift, array $data): CashierShift
    {
        abort_if($shift->status !== 'open', 422, 'Shift ini sudah ditutup.');

        return DB::transaction(function () use ($shift, $data) {
            // Compute summary from orders linked to this shift
            $orders = SaleOrder::where('cashier_shift_id', $shift->id)
                ->where('status', 'completed')
                ->with('payments')
                ->get();

            $totalSales = $orders->sum('total_amount');
            $transactionCount = $orders->count();

            // Payment breakdown by method
            $breakdown = [];
            foreach ($orders as $order) {
                foreach ($order->payments as $payment) {
                    $method = $payment->method;
                    $breakdown[$method] = ($breakdown[$method] ?? 0) + (float) $payment->amount;
                }
            }

            $cashSales    = $breakdown['cash'] ?? 0;
            $expectedCash = (float) $shift->opening_cash + $cashSales;
            $closingCash  = (float) ($data['closing_cash'] ?? 0);
            $difference   = $closingCash - $expectedCash;

            $shift->update([
                'status'              => 'closed',
                'closing_cash'        => $closingCash,
                'expected_cash'       => $expectedCash,
                'cash_difference'     => $difference,
                'transaction_count'   => $transactionCount,
                'total_sales'         => $totalSales,
                'payment_breakdown'   => $breakdown,
                'closing_note'        => $data['closing_note'] ?? null,
                'closed_at'           => now(),
            ]);

            return $shift->fresh(['cashier:id,name']);
        });
    }

    /**
     * List shifts for a business with optional filters.
     *
     * @param  array{user_id?: string|null, status?: string|null, date?: string|null}  $filters
     */
    public function list(string $businessId, array $filters = [])
    {
        $query = CashierShift::with('cashier:id,name')
            ->where('business_id', $businessId);

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date'])) {
            $query->whereDate('opened_at', $filters['date']);
        }

        return $query->orderByDesc('opened_at')->limit(100)->get();
    }

    /**
     * Associate a sale order with the cashier's active shift.
     * Called during order creation. Silently skips if no shift is active.
     */
    public function attachShiftToOrder(SaleOrder $order): void
    {
        if ($order->cashier_shift_id !== null) {
            return; // already linked
        }

        $shift = $this->activeShift($order->business_id, (string) $order->user_id);

        if ($shift !== null) {
            $order->update(['cashier_shift_id' => $shift->id]);
        }
    }
}
