<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\DailyStock;
use Modules\Sales\Models\SaleOrder;

class DailyStockService
{
    /**
     * Open the day by recording opening quantities for each product.
     * Uses updateOrCreate so re-opening resets sold_qty and closing_qty.
     *
     * @param  array<int, array{product_id: string, product_name: string, opening_qty: int}>  $items
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function openDay(string $businessId, string $date, array $items)
    {
        return DB::transaction(function () use ($businessId, $date, $items) {
            return collect($items)->map(fn ($item) => DailyStock::updateOrCreate(
                [
                    'business_id' => $businessId,
                    'date'        => $date,
                    'product_id'  => $item['product_id'],
                ],
                [
                    'product_name' => $item['product_name'],
                    'opening_qty'  => $item['opening_qty'],
                    'sold_qty'     => 0,
                    'closing_qty'  => null,
                    'status'       => 'open',
                ]
            ));
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
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getDay(string $businessId, string $date)
    {
        return DailyStock::where('business_id', $businessId)
            ->where('date', $date)
            ->orderBy('product_name')
            ->get();
    }
}
