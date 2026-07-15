<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Product;
use Modules\Inventory\Models\DailyStock;
use Modules\Inventory\Models\Inventory;
use Modules\Sales\Models\SaleOrder;

class DailyStockService
{
    /**
     * Open the day by recording opening quantities for each product.
     * Uses updateOrCreate so re-opening resets sold_qty and closing_qty.
     * Automatically populates price and image_url from the Catalog.
     *
     * @param  array<int, array{product_id: string, product_name: string, opening_qty: int}>  $items
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function openDay(string $businessId, string $date, array $items)
    {
        return DB::transaction(function () use ($businessId, $date, $items) {
            // Preload product data for price/image enrichment
            $productIds = collect($items)->pluck('product_id')->filter()->unique()->toArray();
            $products = Product::whereIn('id', $productIds)
                ->get(['id', 'price', 'image_url'])
                ->keyBy('id');

            return collect($items)->map(function ($item) use ($businessId, $date, $products) {
                $product = $products->get($item['product_id']);

                return DailyStock::updateOrCreate(
                    [
                        'business_id' => $businessId,
                        'date'        => $date,
                        'product_id'  => $item['product_id'],
                    ],
                    [
                        'product_name'  => $item['product_name'],
                        'price'         => $product?->price,
                        'image_url'     => $product?->image_url,
                        'opening_qty'   => $item['opening_qty'],
                        'adjustment_qty' => 0,
                        'sold_qty'      => 0,
                        'closing_qty'   => null,
                        'status'        => 'open',
                    ]
                );
            });
        });
    }

    /**
     * Increment sold_qty for each sold item against today's open daily stock.
     * Called by the SaleOrderCompleted listener.
     */
    public function recordSale(SaleOrder $order): void
    {
        $order->loadMissing('items');
        $date = now()->toDateString();

        foreach ($order->items as $item) {
            DailyStock::where('business_id', $order->business_id)
                ->where('date', $date)
                ->where('product_id', $item->product_id)
                ->where('status', 'open')
                ->increment('sold_qty', $item->quantity);
        }
    }

    /**
     * Close the day: compute closing_qty and mark all open records as closed.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function closeDay(string $businessId, string $date)
    {
        return DB::transaction(function () use ($businessId, $date) {
            $stocks = DailyStock::where('business_id', $businessId)
                ->where('date', $date)
                ->where('status', 'open')
                ->get();

            foreach ($stocks as $stock) {
                $stock->update([
                    'closing_qty' => max(0, $stock->opening_qty - $stock->sold_qty),
                    'status'      => 'closed',
                ]);
            }

            return $stocks->fresh();
        });
    }

    /**
     * Fetch all daily stock records for a given business and date.
     * Enriches with current inventory stock level.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getDay(string $businessId, string $date)
    {
        $stocks = DailyStock::where('business_id', $businessId)
            ->where('date', $date)
            ->orderBy('product_name')
            ->get();

        // Enrich with current inventory stock
        $productIds = $stocks->pluck('product_id')->filter()->unique()->toArray();
        if (! empty($productIds)) {
            $inventories = Inventory::where('business_id', $businessId)
                ->whereIn('product_id', $productIds)
                ->get(['product_id', 'quantity'])
                ->keyBy('product_id');

            $stocks->each(function ($stock) use ($inventories) {
                $inv = $inventories->get($stock->product_id);
                $stock->current_stock = $inv?->quantity ?? null;
                // Remaining: opening + adjustment - sold
                $stock->remaining_qty = max(0, $stock->opening_qty + $stock->adjustment_qty - $stock->sold_qty);
            });
        }

        return $stocks;
    }

    /**
     * Get all active products for daily stock input, with prices and current inventory.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getProductsForStockInput(string $businessId)
    {
        $products = Product::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'image_url', 'category_id']);

        // Load current inventory
        $productIds = $products->pluck('id')->toArray();
        $inventories = Inventory::where('business_id', $businessId)
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'quantity', 'min_stock'])
            ->keyBy('product_id');

        return $products->map(function ($product) use ($inventories) {
            $inv = $inventories->get($product->id);
            return [
                'id'            => $product->id,
                'name'          => $product->name,
                'price'         => $product->price,
                'image_url'     => $product->image_url,
                'current_stock' => $inv?->quantity ?? 0,
                'min_stock'     => $inv?->min_stock ?? 0,
            ];
        });
    }

    /**
     * Apply a stock adjustment to an open daily stock record.
     */
    public function adjustStock(string $businessId, string $date, string $productId, int $qty, ?string $note = null): DailyStock
    {
        $stock = DailyStock::where('business_id', $businessId)
            ->where('date', $date)
            ->where('product_id', $productId)
            ->where('status', 'open')
            ->firstOrFail();

        $stock->increment('adjustment_qty', $qty);
        $stock->update(['adjustment_note' => $note]);

        return $stock->fresh();
    }
}
