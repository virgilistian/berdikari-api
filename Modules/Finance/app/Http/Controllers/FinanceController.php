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
     * "Today" as the frontend date picker computes it (browser-local, WIB for
     * Indonesian users) — not the server's UTC `today()`, which lags behind
     * during the first hours of each Indonesian calendar day and rejects
     * valid same-day dates as being in the future.
     */
    private function todayForValidation(): string
    {
        return now('Asia/Jakarta')->toDateString();
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

        if ($request->filled('source_type')) {
            $query->where('source_type', $request->source_type);
        }

        if ($request->filled('source_id')) {
            $query->where('source_id', $request->source_id);
        }

        return response()->json([
            'data' => $query->orderByDesc('occurred_at')->limit(200)->get(),
        ]);
    }

    /**
     * Tambah pemasukan / pengeluaran
     *
     * `shift_id` opsional: menandai pengeluaran operasional yang dicatat kasir
     * selama shift aktif (mis. belanja mendadak). Hanya berlaku untuk `type=expense`.
     * `client_uuid` opsional: kunci idempotensi dari perangkat (offline sync) —
     * mengirim ulang `client_uuid` yang sama mengembalikan entri yang sudah ada,
     * bukan membuat duplikat.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'        => 'required|in:income,expense',
            'amount'      => 'required|numeric|min:0',
            'category'    => 'required|string|max:100',
            'note'        => 'nullable|string|max:1000',
            'occurred_at' => ['nullable', 'date', 'before_or_equal:'.$this->todayForValidation()],
            'business_id' => ['nullable', 'uuid', \Illuminate\Validation\Rule::in([$this->businessId($request)])],
            'shift_id'    => 'nullable|uuid',
            'client_uuid' => 'nullable|uuid',
        ], [
            'occurred_at.before_or_equal' => 'Tanggal transaksi tidak boleh di masa depan.',
        ]);

        if (! empty($data['shift_id']) && $data['type'] !== 'expense') {
            abort(422, 'Transaksi yang tertaut ke shift kasir hanya untuk pengeluaran.');
        }

        // Deny-by-default: a shift-linked expense needs pos.expense; any other
        // manual entry needs the broader finance.create permission.
        abort_unless(
            $request->user()?->can(! empty($data['shift_id']) ? 'pos.expense' : 'finance.create'),
            403,
            'Anda tidak memiliki izin untuk mencatat transaksi ini.'
        );

        $businessId = $data['business_id'] ?? $this->businessId($request);

        // Offline-sync idempotency: the same client entry (identified by
        // client_uuid) must never be recorded twice — return the original.
        if (! empty($data['client_uuid'])) {
            $existing = FinanceEntry::withoutGlobalScopes()
                ->with('business:id,name')
                ->where('business_id', $businessId)
                ->where('client_uuid', $data['client_uuid'])
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Transaksi berhasil dicatat.',
                    'data'    => $existing,
                ], 201);
            }
        }

        $entry = FinanceEntry::create([
            'business_id' => $businessId,
            'client_uuid' => $data['client_uuid'] ?? null,
            'type'        => $data['type'],
            'amount'      => $data['amount'],
            'category'    => $data['category'],
            'note'        => $data['note'] ?? null,
            'source_type' => ! empty($data['shift_id']) ? 'shift_expense' : 'manual',
            'source_id'   => $data['shift_id'] ?? null,
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
     * Perbarui transaksi (hanya entri manual)
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $entry = FinanceEntry::where('business_id', $this->businessId($request))->findOrFail($id);

        if ($entry->source_type !== 'manual') {
            return response()->json([
                'message' => 'Transaksi otomatis dari penjualan tidak dapat diubah.',
            ], 422);
        }

        $data = $request->validate([
            'type'        => 'required|in:income,expense',
            'amount'      => 'required|numeric|min:0',
            'category'    => 'required|string|max:100',
            'note'        => 'nullable|string|max:1000',
            'occurred_at' => ['nullable', 'date', 'before_or_equal:'.$this->todayForValidation()],
        ], [
            'occurred_at.before_or_equal' => 'Tanggal transaksi tidak boleh di masa depan.',
        ]);

        $entry->update([
            'type'        => $data['type'],
            'amount'      => $data['amount'],
            'category'    => $data['category'],
            'note'        => $data['note'] ?? null,
            'occurred_at' => $data['occurred_at'] ?? $entry->occurred_at,
        ]);

        $entry->load('business:id,name');

        return response()->json([
            'message' => 'Transaksi berhasil diperbarui.',
            'data'    => $entry,
        ]);
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
