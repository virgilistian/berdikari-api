<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Sales\Models\SaleOrder;
use Modules\Sales\Services\SalesService;

/**
 * @tags Sales — Pesanan (POS Lifecycle)
 */
class SaleOrderController extends Controller
{
    public function __construct(private SalesService $service) {}

    private function businessId(Request $request): string
    {
        return Auth::user()?->business_id ?? (string) $request->input('business_id');
    }

    private function findOrder(Request $request, string $id): SaleOrder
    {
        return SaleOrder::with(['items', 'payments'])
            ->where('business_id', $this->businessId($request))
            ->findOrFail($id);
    }

    /**
     * Daftar pesanan
     *
     * Filter opsional: `status` (open/completed/cancelled/refunded),
     * `payment_status` (unpaid/partial/paid) dan `date` (Y-m-d).
     */
    public function index(Request $request): JsonResponse
    {
        $orders = $this->service->listOrders($this->businessId($request), [
            'status'         => $request->input('status'),
            'payment_status' => $request->input('payment_status'),
            'date'           => $request->input('date'),
        ]);

        return response()->json(['data' => $orders]);
    }

    /**
     * Buat pesanan
     *
     * `action`: `hold` untuk menyimpan/menahan pesanan, `complete` untuk langsung selesai.
     * `payments[]` opsional untuk pembayaran penuh, sebagian, atau kosong (bayar nanti).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'business_id'         => 'required|uuid',
            'client_uuid'         => 'nullable|uuid',
            'action'              => 'nullable|in:hold,complete',
            'items'               => 'required|array|min:1',
            'items.*.product_id'  => 'required|uuid',
            'items.*.quantity'    => 'required|integer|min:1',
            'items.*.unit_price'  => 'required|numeric|min:0',
            'customer_name'       => 'nullable|string|max:255',
            'note'                => 'nullable|string|max:255',
            'payments'            => 'nullable|array',
            'payments.*.amount'   => 'required_with:payments|numeric|min:0',
            'payments.*.method'   => 'nullable|string|max:30',
        ]);

        $order = $this->service->createOrder(
            $this->businessId($request),
            Auth::id(),
            $data,
        );

        return response()->json([
            'message' => $order->status === 'open' ? 'Pesanan disimpan.' : 'Pesanan selesai.',
            'data'    => $order,
        ], 201);
    }

    /**
     * Ringkasan penjualan (dashboard & laporan)
     *
     * Agregasi penjualan pada rentang tanggal `from`–`to` (Y-m-d, default hari
     * ini): total penjualan, jumlah transaksi, rata-rata nota, penjualan
     * harian, produk terlaris, dan rincian metode pembayaran.
     */
    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to'   => 'nullable|date_format:Y-m-d|after_or_equal:from',
        ]);

        return response()->json([
            'data' => $this->service->summary(
                $this->businessId($request),
                $request->input('from'),
                $request->input('to'),
            ),
        ]);
    }

    /**
     * Detail pesanan (data struk)
     */
    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json(['data' => $this->findOrder($request, $id)]);
    }

    /**
     * Selesaikan pesanan tersimpan
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'payments'          => 'nullable|array',
            'payments.*.amount' => 'required_with:payments|numeric|min:0',
            'payments.*.method' => 'nullable|string|max:30',
        ]);

        $order = $this->service->completeOrder(
            $this->findOrder($request, $id),
            $data['payments'] ?? [],
        );

        return response()->json(['message' => 'Pesanan selesai.', 'data' => $order]);
    }

    /**
     * Tambah pembayaran (bayar sebagian / pelunasan)
     */
    public function pay(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
            'method' => 'nullable|string|max:30',
            'note'   => 'nullable|string|max:255',
        ]);

        $order = $this->findOrder($request, $id);
        $this->service->addPayment($order, (float) $data['amount'], $data['method'] ?? 'cash', $data['note'] ?? null);

        return response()->json([
            'message' => 'Pembayaran berhasil dicatat.',
            'data'    => $order->fresh(['items', 'payments']),
        ]);
    }

    /**
     * Batalkan pesanan tersimpan
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $order = $this->service->cancelOrder($this->findOrder($request, $id));

        return response()->json(['message' => 'Pesanan dibatalkan.', 'data' => $order]);
    }

    /**
     * Refund pesanan selesai
     */
    public function refund(Request $request, string $id): JsonResponse
    {
        $order = $this->service->refundOrder($this->findOrder($request, $id));

        return response()->json(['message' => 'Pesanan berhasil direfund.', 'data' => $order]);
    }
}
