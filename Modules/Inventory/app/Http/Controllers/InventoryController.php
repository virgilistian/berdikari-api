<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * @tags Inventory — Stok Realtime
 */
class InventoryController extends Controller
{
    /**
     * Daftar stok realtime
     *
     * Mengembalikan daftar stok produk secara realtime untuk bisnis pengguna.
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "product_id": "uuid",
     *       "product_name": "Nasi Kucing",
     *       "current_stock": 32
     *     }
     *   ]
     * }
     */
    public function index()
    {
        return view('inventory::index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @hideFromAPIDocumentation
     */
    public function create()
    {
        return view('inventory::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Detail stok produk
     *
     * @response 200 {"data": {"product_id": "uuid", "product_name": "Nasi Kucing", "current_stock": 32}}
     * @response 404 {"message": "Not found."}
     */
    public function show($id)
    {
        return view('inventory::show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @hideFromAPIDocumentation
     */
    public function edit($id)
    {
        return view('inventory::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id) {}
}

