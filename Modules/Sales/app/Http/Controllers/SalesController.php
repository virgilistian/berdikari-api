<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Sales\Models\SaleOrder;
use Modules\Sales\Models\SaleOrderItem;
use Modules\Sales\Events\SaleOrderCompleted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * @tags Sales — POS Checkout
 */
class SalesController extends Controller
{
    /**
     * Checkout POS
     *
     * Memproses transaksi penjualan dari kasir (Point of Sale).
     * Setelah checkout berhasil, event `SaleOrderCompleted` dikirim untuk
     * memperbarui stok inventori secara otomatis.
     *
     * @response 201 {
     *   "message": "Checkout successful",
     *   "order": {
     *     "id": "uuid",
     *     "business_id": "uuid",
     *     "user_id": "uuid",
     *     "status": "completed",
     *     "total_amount": 25000,
     *     "items": [
     *       {
     *         "id": "uuid",
     *         "product_id": "uuid",
     *         "quantity": 2,
     *         "unit_price": 5000,
     *         "subtotal": 10000
     *       }
     *     ]
     *   }
     * }
     * @response 422 {"message": "Validation failed", "errors": {"items": ["The items field is required."]}}
     * @response 500 {"message": "Checkout failed", "error": "Database error detail"}
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'business_id' => 'required|uuid',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|uuid',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $totalAmount = collect($request->items)->sum(function($item) {
                return $item['quantity'] * $item['unit_price'];
            });

            // Create Order
            $order = SaleOrder::create([
                'business_id' => $request->business_id,
                'user_id' => Auth::id(), // Can be null if not using Auth
                'status' => 'completed',
                'total_amount' => $totalAmount
            ]);

            // Create Order Items
            foreach ($request->items as $item) {
                SaleOrderItem::create([
                    'sale_order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['quantity'] * $item['unit_price']
                ]);
            }

            DB::commit();

            // Fire event for Inventory reduction etc.
            event(new SaleOrderCompleted($order));

            return response()->json([
                'message' => 'Checkout successful',
                'order' => $order->load('items') // Note: We might need a relationship in SaleOrder model
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Checkout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
