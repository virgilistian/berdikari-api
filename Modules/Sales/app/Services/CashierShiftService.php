<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\FinanceEntry;
use Modules\Inventory\Services\DailyStockService;
use Modules\Sales\Models\CashierShift;
use Modules\Sales\Models\SaleOrder;
use Modules\Sales\Models\SalePayment;

class CashierShiftService
{
    public function __construct(private DailyStockService $dailyStockService) {}

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
     * Compute live sales stats (total, count, payment breakdown) from orders
     * linked to this shift. Shared by the open-shift live preview and the
     * final close calculation so both always agree with the same source of
     * truth (completed SaleOrder rows).
     *
     * @return array{0: float, 1: int, 2: array<string, float>}
     */
    private function computeOrderStats(CashierShift $shift): array
    {
        $orders = SaleOrder::where('cashier_shift_id', $shift->id)
            ->where('status', 'completed')
            ->with('payments')
            ->get();

        $totalSales = (float) $orders->sum('total_amount');
        $transactionCount = $orders->count();

        $breakdown = [];
        foreach ($orders as $order) {
            foreach ($order->payments as $payment) {
                $method = $payment->method;
                $breakdown[$method] = ($breakdown[$method] ?? 0) + (float) $payment->amount;
            }
        }

        return [$totalSales, $transactionCount, $breakdown];
    }

    private function computeTotalExpenses(CashierShift $shift): float
    {
        return (float) FinanceEntry::where('business_id', $shift->business_id)
            ->where('type', 'expense')
            ->where('source_type', 'shift_expense')
            ->where('source_id', $shift->id)
            ->sum('amount');
    }

    /**
     * Attach live-computed summary fields onto a still-OPEN shift so the
     * "Tutup Shift" preview and the active-shift banner always show up to
     * date sales/transaction data. Not persisted — final numbers are only
     * written by close().
     */
    public function withLiveSummary(CashierShift $shift): CashierShift
    {
        if ($shift->status !== 'open') {
            return $shift;
        }

        [$totalSales, $transactionCount, $breakdown] = $this->computeOrderStats($shift);
        $totalExpenses = $this->computeTotalExpenses($shift);

        $shift->total_sales       = $totalSales;
        $shift->transaction_count = $transactionCount;
        $shift->payment_breakdown = $breakdown;
        $shift->total_expenses    = $totalExpenses;
        $shift->net_income        = $totalSales - $totalExpenses;

        return $shift;
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
            [$totalSales, $transactionCount, $breakdown] = $this->computeOrderStats($shift);
            $totalExpenses = $this->computeTotalExpenses($shift);

            $cashSales    = $breakdown['cash'] ?? 0;
            $expectedCash = (float) $shift->opening_cash + $cashSales - $totalExpenses;
            $closingCash  = (float) ($data['closing_cash'] ?? 0);
            $difference   = $closingCash - $expectedCash;

            // Finalize today's daily stock opname (kitchen input) and snapshot it
            // on the shift for the shift summary — no separate "close stock" action.
            // Only freeze it once this is the LAST open shift for the day: daily_stocks
            // is scoped per business+date, not per shift, so closing it while another
            // cashier's shift is still open would silently stop that shift's sales and
            // adjustments from being recorded (recordSale/adjustStock only touch
            // status='open' rows) — the exact cause of Stock-page vs shift-close drift.
            $today = now()->toDateString();
            $isLastOpenShift = ! CashierShift::where('business_id', $shift->business_id)
                ->where('status', 'open')
                ->where('id', '!=', $shift->id)
                ->exists();

            $dailyStocks = $isLastOpenShift
                ? $this->dailyStockService->closeDay($shift->business_id, $today)
                : $this->dailyStockService->getDay($shift->business_id, $today);

            $stockSummary = $dailyStocks->map(fn ($s) => [
                'product_id'      => $s->product_id,
                'product_name'    => $s->product_name,
                'opening_qty'     => $s->opening_qty,
                'sold_qty'        => $s->sold_qty,
                'adjustment_qty'  => $s->adjustment_qty,
                'adjustment_note' => $s->adjustment_note,
                'closing_qty'     => $s->closing_qty ?? max(0, $s->opening_qty + $s->adjustment_qty - $s->sold_qty),
            ])->values()->all();

            $shift->update([
                'status'              => 'closed',
                'closing_cash'        => $closingCash,
                'expected_cash'       => $expectedCash,
                'cash_difference'     => $difference,
                'transaction_count'   => $transactionCount,
                'total_sales'         => $totalSales,
                'total_expenses'      => $totalExpenses,
                'net_income'          => $totalSales - $totalExpenses,
                'payment_breakdown'   => $breakdown,
                'stock_summary'       => $stockSummary,
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
