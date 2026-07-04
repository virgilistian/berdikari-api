<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Sales\Models\SaleOrder;
use Modules\Sales\Models\SaleOrderItem;
use Modules\Sales\Events\SaleOrderCompleted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SalesController extends Controller
{
    /**
     * Process checkout from POS
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
