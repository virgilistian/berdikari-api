<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Inventory\Services\DailyStockService;

class DailyStockController extends Controller
{
    public function __construct(private DailyStockService $service) {}

    /**
     * GET /v1/inventory/daily-stock/{date}?business_id=<uuid>
     * Returns all daily stock records for the given date.
     */
    public function show(Request $request, string $date)
    {
        $request->validate(['business_id' => 'required|uuid']);

        $stocks = $this->service->getDay($request->business_id, $date);

        return response()->json(['data' => $stocks]);
    }

    /**
     * POST /v1/inventory/daily-stock/open
     * Records opening quantities for each product, opening the day.
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
     * POST /v1/inventory/daily-stock/close
     * Closes the day, computing closing_qty for every open record.
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
