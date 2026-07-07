<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Finance\Models\FinanceEntry;

/**
 * @tags Finance — Arus Kas
 */
class FinanceController extends Controller
{
    private function businessId(Request $request): string
    {
        return Auth::user()?->business_id ?? (string) $request->input('business_id');
    }

    /**
     * Daftar arus kas
     *
     * Filter opsional: `type` (income/expense), `category`, `from`, `to` (Y-m-d), `business_id`.
     */
    public function index(Request $request): JsonResponse
    {
        $query = FinanceEntry::with('business:id,name')
            ->where('business_id', $this->businessId($request));

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('from')) {
            $query->whereDate('occurred_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('occurred_at', '<=', $request->to);
        }

        if ($request->filled('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        return response()->json([
            'data' => $query->orderByDesc('occurred_at')->limit(200)->get(),
        ]);
    }

    /**
     * Tambah pemasukan / pengeluaran
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'        => 'required|in:income,expense',
            'amount'      => 'required|numeric|min:0',
            'category'    => 'required|string|max:100',
            'note'        => 'nullable|string|max:1000',
            'occurred_at' => 'nullable|date',
            'business_id' => ['nullable', 'uuid', \Illuminate\Validation\Rule::in([$this->businessId($request)])],
        ]);

        $entry = FinanceEntry::create([
            ...$data,
            'business_id' => $data['business_id'] ?? $this->businessId($request),
            'source_type' => 'manual',
            'occurred_at' => $data['occurred_at'] ?? now(),
        ]);

        $entry->load('business:id,name');

        return response()->json([
            'message' => 'Transaksi berhasil dicatat.',
            'data'    => $entry,
        ], 201);
    }

    /**
     * Detail transaksi
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $entry = FinanceEntry::with('business:id,name')
            ->where('business_id', $this->businessId($request))
            ->findOrFail($id);

        return response()->json(['data' => $entry]);
    }

    /**
     * Hapus transaksi (hanya entri manual)
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $entry = FinanceEntry::where('business_id', $this->businessId($request))->findOrFail($id);

        if ($entry->source_type !== 'manual') {
            return response()->json([
                'message' => 'Transaksi otomatis dari penjualan tidak dapat dihapus.',
            ], 422);
        }

        $entry->delete();

        return response()->json(['message' => 'Transaksi berhasil dihapus.']);
    }

    /**
     * Ringkasan keuangan
     *
     * Total pemasukan, pengeluaran dan saldo bersih untuk rentang tanggal.
     * Default rentang: hari ini. Menyertakan rincian per kategori.
     */
    public function summary(Request $request): JsonResponse
    {
        $businessId = $this->businessId($request);
        $from = $request->input('from', now()->toDateString());
        $to   = $request->input('to', now()->toDateString());

        $entries = FinanceEntry::where('business_id', $businessId)
            ->whereDate('occurred_at', '>=', $from)
            ->whereDate('occurred_at', '<=', $to)
            ->get();

        $income  = round((float) $entries->where('type', 'income')->sum('amount'), 2);
        $expense = round((float) $entries->where('type', 'expense')->sum('amount'), 2);

        return response()->json([
            'data' => [
                'from'         => $from,
                'to'           => $to,
                'total_income' => $income,
                'total_expense' => $expense,
                'net'          => round($income - $expense, 2),
                'income_by_category' => $entries->where('type', 'income')
                    ->groupBy('category')
                    ->map(fn ($g) => round((float) $g->sum('amount'), 2)),
                'expense_by_category' => $entries->where('type', 'expense')
                    ->groupBy('category')
                    ->map(fn ($g) => round((float) $g->sum('amount'), 2)),
            ],
        ]);
    }
}
