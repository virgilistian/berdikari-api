<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Services\InventoryService;

/**
 * @tags Inventory — Stok Realtime
 */
class InventoryController extends Controller
{
    public function __construct(private InventoryService $service) {}

    /**
     * Resolve the active business id from the authenticated user (fallback to request).
     */
    private function businessId(Request $request): string
    {
        return $request->user()?->business_id ?? (string) $request->input('business_id');
    }

    /**
     * Daftar stok realtime
     *
     * Mengembalikan daftar stok produk beserta valuasi dan penanda stok menipis.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->list($this->businessId($request)),
        ]);
    }

    /**
     * Ringkasan valuasi stok
     *
     * Total produk, total kuantitas, nilai stok (harga beli) dan nilai jual.
     */
    public function summary(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->summary($this->businessId($request)),
        ]);
    }

    /**
     * Stok menipis
     *
     * Daftar produk yang kuantitasnya berada di bawah atau sama dengan ambang batas.
     */
    public function lowStock(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->lowStock($this->businessId($request)),
        ]);
    }

    /**
     * Detail stok produk
     *
     * Menampilkan stok saat ini dan 100 riwayat pergerakan terakhir.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $businessId = $this->businessId($request);
        $inventory  = $this->service->ensureRecord($businessId, $id);

        return response()->json([
            'data' => [
                'stock'     => $this->service->list($businessId)->firstWhere('product_id', $id),
                'movements' => $this->service->movements($businessId, $id),
            ],
        ]);
    }

    /**
     * Riwayat pergerakan stok
     */
    public function movements(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'data' => $this->service->movements($this->businessId($request), $id),
        ]);
    }

    /**
     * Stok masuk (pembelian / penerimaan barang)
     */
    public function receive(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|uuid',
            'quantity'   => 'required|integer|min:1',
            'unit_cost'  => 'nullable|numeric|min:0',
            'reason'     => 'nullable|string|max:255',
        ]);

        $inventory = $this->service->receive(
            $this->businessId($request),
            $data['product_id'],
            $data['quantity'],
            $data['unit_cost'] ?? null,
            $data['reason'] ?? null,
        );

        return response()->json([
            'message' => 'Stok masuk berhasil dicatat.',
            'data'    => $inventory,
        ], 201);
    }

    /**
     * Penyesuaian stok (koreksi / opname)
     */
    public function adjust(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|uuid',
            'quantity'   => 'required|integer|min:0',
            'reason'     => 'nullable|string|max:255',
        ]);

        $inventory = $this->service->adjust(
            $this->businessId($request),
            $data['product_id'],
            $data['quantity'],
            $data['reason'] ?? null,
        );

        return response()->json([
            'message' => 'Stok berhasil disesuaikan.',
            'data'    => $inventory,
        ]);
    }

    /**
     * Atur ambang batas stok menipis
     */
    public function setMinStock(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'min_stock' => 'required|integer|min:0',
        ]);

        $inventory = $this->service->setMinStock(
            $this->businessId($request),
            $id,
            $data['min_stock'],
        );

        return response()->json([
            'message' => 'Ambang batas stok berhasil diperbarui.',
            'data'    => $inventory,
        ]);
    }
}

