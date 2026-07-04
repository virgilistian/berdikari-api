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
}
