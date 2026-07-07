<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Product;
use Modules\Inventory\Models\Inventory;
use Modules\Inventory\Models\InventoryMovement;
use Modules\Sales\Models\SaleOrder;

class InventoryService
{
    /**
     * Get (or lazily create) the stock record for a product in a business.
     */
    public function ensureRecord(string $businessId, string $productId): Inventory
    {
        return Inventory::firstOrCreate(
            ['business_id' => $businessId, 'product_id' => $productId],
            ['quantity' => 0, 'min_stock' => 0]
        );
    }

    /**
     * Realtime stock list enriched with product name, prices, valuation and
     * a low-stock flag.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function list(string $businessId)
    {
        return Inventory::with('product')
            ->where('business_id', $businessId)
            ->get()
            ->map(fn (Inventory $inv) => $this->present($inv))
            ->sortBy('product_name')
            ->values();
    }

    /**
     * Aggregate valuation summary for the whole business.
     *
     * @return array<string, mixed>
     */
    public function summary(string $businessId): array
    {
        $rows = $this->list($businessId);

        return [
            'total_products'   => $rows->count(),
            'total_quantity'   => (int) $rows->sum('quantity'),
            'stock_value'      => round((float) $rows->sum('stock_value'), 2),
            'retail_value'     => round((float) $rows->sum('retail_value'), 2),
            'low_stock_count'  => $rows->where('is_low', true)->count(),
            'out_of_stock_count' => $rows->where('quantity', 0)->count(),
        ];
    }

    /**
     * Products at or below their low-stock threshold.
     */
    public function lowStock(string $businessId)
    {
        return $this->list($businessId)
            ->filter(fn ($row) => $row['is_low'])
            ->values();
    }

    /**
     * Movement history for a single product (newest first).
     */
    public function movements(string $businessId, string $productId)
    {
        return InventoryMovement::where('business_id', $businessId)
            ->where('product_id', $productId)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
    }

    /**
     * Receive stock (stok masuk / pembelian). Increases quantity.
     */
    public function receive(string $businessId, string $productId, int $quantity, ?float $unitCost = null, ?string $reason = null): Inventory
    {
        return DB::transaction(function () use ($businessId, $productId, $quantity, $unitCost, $reason) {
            $inv = $this->ensureRecord($businessId, $productId);
            $inv->increment('quantity', $quantity);
            $inv->refresh();

            $this->recordMovement($inv, 'in', $quantity, $unitCost, $reason ?? 'Stok masuk');

            return $inv;
        });
    }

    /**
     * Adjust stock to an absolute quantity (stok opname / koreksi).
     * Records the signed delta as an adjustment movement.
     */
    public function adjust(string $businessId, string $productId, int $newQuantity, ?string $reason = null): Inventory
    {
        return DB::transaction(function () use ($businessId, $productId, $newQuantity, $reason) {
            $inv = $this->ensureRecord($businessId, $productId);
            $delta = $newQuantity - $inv->quantity;
            $inv->update(['quantity' => max(0, $newQuantity)]);

            $this->recordMovement($inv, 'adjustment', $delta, null, $reason ?? 'Penyesuaian stok');

            return $inv;
        });
    }

    /**
     * Set the low-stock threshold for a product.
     */
    public function setMinStock(string $businessId, string $productId, int $minStock): Inventory
    {
        $inv = $this->ensureRecord($businessId, $productId);
        $inv->update(['min_stock' => max(0, $minStock)]);

        return $inv;
    }

    /**
     * Deduct stock for a completed sale order. Called by the sales listener.
     * Uses signed negative quantity movements of type "out".
     */
    public function deductForSale(SaleOrder $order): void
    {
        $order->loadMissing('items');

        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $inv = $this->ensureRecord($order->business_id, $item->product_id);
                $inv->decrement('quantity', $item->quantity);
                $inv->refresh();

                $this->recordMovement(
                    $inv,
                    'out',
                    -1 * $item->quantity,
                    null,
                    'Penjualan',
                    'sale_order',
                    $order->id
                );
            }
        });
    }

    /**
     * Restore stock when a completed order is refunded/cancelled.
     */
    public function restoreForRefund(SaleOrder $order): void
    {
        $order->loadMissing('items');

        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $inv = $this->ensureRecord($order->business_id, $item->product_id);
                $inv->increment('quantity', $item->quantity);
                $inv->refresh();

                $this->recordMovement(
                    $inv,
                    'in',
                    $item->quantity,
                    null,
                    'Pengembalian penjualan',
                    'sale_order_refund',
                    $order->id
                );
            }
        });
    }

    /**
     * Shape an Inventory model into the API/list representation.
     *
     * @return array<string, mixed>
     */
    private function present(Inventory $inv): array
    {
        $product      = $inv->product;
        $purchase     = (float) ($product->purchase_price ?? 0);
        $sellingPrice = (float) ($product->price ?? 0);

        return [
            'product_id'     => $inv->product_id,
            'product_name'   => $product->name ?? '—',
            'quantity'       => (int) $inv->quantity,
            'min_stock'      => (int) $inv->min_stock,
            'purchase_price' => $purchase,
            'selling_price'  => $sellingPrice,
            'stock_value'    => round($purchase * $inv->quantity, 2),
            'retail_value'   => round($sellingPrice * $inv->quantity, 2),
            'is_low'         => $inv->quantity <= $inv->min_stock,
            'updated_at'     => $inv->updated_at,
        ];
    }

    /**
     * Persist a single movement row with running balance.
     */
    private function recordMovement(
        Inventory $inv,
        string $type,
        int $quantity,
        ?float $unitCost,
        ?string $reason,
        ?string $referenceType = null,
        ?string $referenceId = null
    ): InventoryMovement {
        $cost = $unitCost ?? (float) ($inv->product->purchase_price ?? 0);

        return InventoryMovement::create([
            'business_id'    => $inv->business_id,
            'inventory_id'   => $inv->id,
            'product_id'     => $inv->product_id,
            'type'           => $type,
            'quantity'       => $quantity,
            'unit_cost'      => $cost,
            'balance_after'  => $inv->quantity,
            'reason'         => $reason,
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
        ]);
    }
}
