<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Sales\Services\SalesService;
use Illuminate\Support\Facades\Auth;

/**
 * @tags Sales — POS Checkout
 */
class SalesController extends Controller
{
    public function __construct(private SalesService $service) {}

    /**
     * Checkout POS
     *
     * Memproses transaksi penjualan tunai dari kasir (Point of Sale).
     * Membuat pesanan berstatus `completed` dan langsung lunas. Setelah berhasil,
     * event `SaleOrderCompleted` dan `SalePaymentReceived` dikirim untuk memperbarui
     * stok inventori dan mencatat pemasukan di modul Keuangan secara otomatis.
     *
     * @response 201 {
     *   "message": "Checkout successful",
     *   "order": {
     *     "id": "uuid",
     *     "order_no": "NOTA-260707-0001",
     *     "status": "completed",
     *     "payment_status": "paid",
     *     "total_amount": 25000,
     *     "paid_amount": 25000,
     *     "change_amount": 0
     *   }
     * }
     * @response 422 {"message": "Validation failed", "errors": {"items": ["The items field is required."]}}
     */
    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'business_id'        => 'required|uuid',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|uuid',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'paid'               => 'nullable|numeric|min:0',
            'method'             => 'nullable|string|max:30',
            'customer_name'      => 'nullable|string|max:255',
            'note'               => 'nullable|string|max:255',
        ]);

        $businessId = Auth::user()?->business_id ?? $validated['business_id'];
        $total = collect($validated['items'])->sum(fn ($i) => $i['quantity'] * $i['unit_price']);

        $order = $this->service->createOrder($businessId, Auth::id(), [
            'items'         => $validated['items'],
            'action'        => 'complete',
            'customer_name' => $validated['customer_name'] ?? null,
            'note'          => $validated['note'] ?? null,
            'payments'      => [[
                'amount' => $validated['paid'] ?? $total,
                'method' => $validated['method'] ?? 'cash',
            ]],
        ]);

        return response()->json([
            'message' => 'Checkout successful',
            'order'   => $order,
        ], 201);
    }
}
