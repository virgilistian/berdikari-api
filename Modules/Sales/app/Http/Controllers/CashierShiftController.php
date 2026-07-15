<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Sales\Models\CashierShift;
use Modules\Sales\Services\CashierShiftService;

/**
 * @tags Sales — Manajemen Shift Kasir
 */
class CashierShiftController extends Controller
{
    public function __construct(private CashierShiftService $service) {}

    private function businessId(): string
    {
        return (string) Auth::user()?->business_id;
    }

    private function userId(): string
    {
        return (string) Auth::id();
    }

    /**
     * Shift aktif saya
     *
     * Mengembalikan shift kasir yang sedang aktif untuk pengguna yang login.
     * Mengembalikan null jika tidak ada shift aktif.
     */
    public function active(): JsonResponse
    {
        $this->authorize('pos.open', CashierShift::class);

        $shift = $this->service->activeShift($this->businessId(), $this->userId());

        return response()->json(['data' => $shift]);
    }

    /**
     * Daftar shift
     *
     * Menampilkan riwayat shift dengan filter opsional.
     *
     * @queryParam status string Filter status (open|closed). Example: closed
     * @queryParam date string Filter tanggal (Y-m-d). Example: 2026-07-07
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('pos.view', CashierShift::class);

        $shifts = $this->service->list($this->businessId(), [
            'status' => $request->input('status'),
            'date'   => $request->input('date'),
        ]);

        return response()->json(['data' => $shifts]);
    }

    /**
     * Detail shift
     */
    public function show(string $id): JsonResponse
    {
        $this->authorize('pos.view', CashierShift::class);

        $shift = CashierShift::with('cashier:id,name')
            ->where('business_id', $this->businessId())
            ->findOrFail($id);

        return response()->json(['data' => $shift]);
    }

    /**
     * Buka shift kasir
     *
     * Membuka shift baru untuk kasir. Hanya satu shift aktif per kasir diizinkan.
     *
     * @bodyParam opening_cash number required Uang kas awal. Example: 500000
     *
     * @response 201 {"message":"Shift berhasil dibuka.","data":{...}}
     * @response 422 {"message":"Anda masih memiliki shift yang sedang aktif."}
     */
    public function open(Request $request): JsonResponse
    {
        $this->authorize('pos.open', CashierShift::class);

        $data = $request->validate([
            'opening_cash' => 'required|numeric|min:0',
        ]);

        $shift = $this->service->open($this->businessId(), $this->userId(), $data);

        return response()->json(['message' => 'Shift berhasil dibuka.', 'data' => $shift], 201);
    }

    /**
     * Tutup shift kasir
     *
     * Menutup shift aktif dengan penghitungan kas dan rekapitulasi penjualan.
     *
     * @bodyParam closing_cash number required Uang kas aktual saat penutupan. Example: 1250000
     * @bodyParam closing_note string Catatan penutupan. Example: Selisih karena kembalian
     *
     * @response 200 {"message":"Shift berhasil ditutup.","data":{...}}
     * @response 422 {"message":"Shift ini sudah ditutup."}
     */
    public function close(Request $request, string $id): JsonResponse
    {
        $this->authorize('pos.close', CashierShift::class);

        $data = $request->validate([
            'closing_cash' => 'required|numeric|min:0',
            'closing_note' => 'nullable|string|max:500',
        ]);

        $shift = CashierShift::where('business_id', $this->businessId())
            ->where('user_id', $this->userId())
            ->findOrFail($id);

        $closed = $this->service->close($shift, $data);

        return response()->json(['message' => 'Shift berhasil ditutup.', 'data' => $closed]);
    }
}
