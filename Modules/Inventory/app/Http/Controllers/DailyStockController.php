<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Inventory\Services\DailyStockService;

/**
 * @tags Inventory — Stok Opname Harian
 */
class DailyStockController extends Controller
{
    public function __construct(private DailyStockService $service) {}

    /**
     * Data stok harian
     *
     * Mengembalikan semua catatan stok opname untuk tanggal tertentu.
     *
     * @queryParam business_id string required UUID bisnis. Example: 550e8400-e29b-41d4-a716-446655440000
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": "uuid",
     *       "product_id": "uuid",
     *       "product_name": "Nasi Kucing",
     *       "date": "2024-01-15",
     *       "opening_qty": 50,
     *       "closing_qty": 32
     *     }
     *   ]
     * }
     * @response 422 {"message": "The business id field is required."}
     */
    public function show(Request $request, string $date)
    {
        $request->validate(['business_id' => 'required|uuid']);

        $stocks = $this->service->getDay($request->business_id, $date);

        return response()->json(['data' => $stocks]);
    }

    /**
     * Buka stok harian
     *
     * Mencatat kuantitas awal setiap produk untuk membuka hari baru.
     * Dipanggil saat kasir membuka toko di pagi hari.
     *
     * @response 201 {
     *   "message": "Stok hari ini berhasil dibuka.",
     *   "data": [
     *     {
     *       "id": "uuid",
     *       "product_id": "uuid",
     *       "product_name": "Nasi Kucing",
     *       "date": "2024-01-15",
     *       "opening_qty": 50,
     *       "closing_qty": null
     *     }
     *   ]
     * }
     * @response 422 {"message": "The date field is required."}
     */
    public function open(Request $request)
    {
        $request->validate([
            'business_id'              => 'required|uuid',
            'date'                     => 'required|date_format:Y-m-d',
            'items'                    => 'required|array|min:1',
            'items.*.product_id'       => 'required|uuid',
            'items.*.product_name'     => 'required|string|max:255',
            'items.*.opening_qty'      => 'required|integer|min:0',
        ]);

        $records = $this->service->openDay(
            $request->business_id,
            $request->date,
            $request->items
        );

        return response()->json([
            'message' => 'Stok hari ini berhasil dibuka.',
            'data'    => $records,
        ], 201);
    }

    /**
     * Tutup stok harian
     *
     * Menutup hari dan menghitung `closing_qty` untuk setiap catatan stok yang terbuka.
     * Dipanggil saat kasir menutup toko di akhir hari.
     *
     * @response 200 {
     *   "message": "Hari berhasil ditutup.",
     *   "data": [
     *     {
     *       "id": "uuid",
     *       "product_name": "Nasi Kucing",
     *       "opening_qty": 50,
     *       "closing_qty": 32,
     *       "sold_qty": 18
     *     }
     *   ]
     * }
     * @response 422 {"message": "The date field is required."}
     */
    public function close(Request $request)
    {
        $request->validate([
            'business_id' => 'required|uuid',
            'date'        => 'required|date_format:Y-m-d',
        ]);

        $recap = $this->service->closeDay($request->business_id, $request->date);

        return response()->json([
            'message' => 'Hari berhasil ditutup.',
            'data'    => $recap,
        ]);
    }

    /**
     * Riwayat stok harian
     *
     * Mengembalikan ringkasan stok harian per tanggal (terbaru lebih dulu).
     *
     * @queryParam business_id string required UUID bisnis. Example: 550e8400-e29b-41d4-a716-446655440000
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "date": "2026-07-17",
     *       "total_menu_items": 12,
     *       "total_opening_qty": 120,
     *       "total_closing_qty": 34,
     *       "status": "closed"
     *     }
     *   ]
     * }
     * @response 422 {"message": "The business id field is required."}
     */
    public function history(Request $request)
    {
        $request->validate(['business_id' => 'required|uuid']);

        $history = $this->service->getHistory($request->business_id);

        return response()->json(['data' => $history]);
    }

    /**
     * Daftar produk untuk stok harian
     *
     * Mengembalikan semua produk aktif dengan harga dan stok tersedia,
     * siap untuk input stok harian.
     *
     * @queryParam business_id string required UUID bisnis. Example: uuid
     */
    public function products(Request $request)
    {
        $request->validate(['business_id' => 'required|uuid']);

        $products = $this->service->getProductsForStockInput($request->business_id);

        return response()->json(['data' => $products]);
    }

    /**
     * Sesuaikan stok harian
     *
     * Menambahkan penyesuaian stok manual (penambahan atau pengurangan).
     *
     * @bodyParam business_id string required UUID bisnis. Example: uuid
     * @bodyParam date string required Tanggal (Y-m-d). Example: 2026-07-07
     * @bodyParam product_id string required UUID produk. Example: uuid
     * @bodyParam adjustment_qty integer required Jumlah penyesuaian (positif/negatif). Example: -5
     * @bodyParam adjustment_note string Alasan penyesuaian. Example: Rusak / terbuang
     *
     * @response 200 {"message":"Penyesuaian stok berhasil.","data":{...}}
     */
    public function adjust(Request $request)
    {
        // Kitchen/inventory staff adjust freely (inventory.update); a cashier may
        // also adjust during shift-closing stock reconciliation (pos.close).
        abort_unless(
            $request->user()?->hasAnyPermission(['inventory.update', 'pos.close']),
            403,
            'Anda tidak memiliki izin untuk menyesuaikan stok.'
        );

        $request->validate([
            'business_id'     => 'required|uuid',
            'date'            => 'required|date_format:Y-m-d',
            'product_id'      => 'required|uuid',
            'adjustment_qty'  => 'required|integer',
            'adjustment_note' => 'required_unless:adjustment_qty,0|nullable|string|max:255',
        ], [
            'adjustment_note.required_unless' => 'Alasan penyesuaian wajib diisi jika jumlah stok diubah.',
        ]);

        $stock = $this->service->adjustStock(
            $request->business_id,
            $request->date,
            $request->product_id,
            (int) $request->adjustment_qty,
            $request->adjustment_note
        );

        return response()->json(['message' => 'Penyesuaian stok berhasil.', 'data' => $stock]);
    }

    /**
     * Hapus draf stok harian
     *
     * Menghapus seluruh catatan stok untuk tanggal tertentu, hanya jika
     * masih berstatus draf (belum berlaku).
     *
     * @queryParam business_id string required UUID bisnis. Example: uuid
     *
     * @response 200 {"message": "Draf stok berhasil dihapus."}
     * @response 422 {"message": "Hanya stok berstatus draf yang bisa dihapus."}
     */
    public function destroy(Request $request, string $date)
    {
        abort_unless(
            $request->user()?->hasPermissionTo('inventory.create'),
            403,
            'Anda tidak memiliki izin untuk menghapus draf stok.'
        );

        $request->validate(['business_id' => 'required|uuid']);

        $this->service->deleteDraftDay($request->business_id, $date);

        return response()->json(['message' => 'Draf stok berhasil dihapus.']);
    }
}
