<?php

namespace Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * @tags Catalog — Produk
 */
class ProductController extends Controller
{
    /**
     * Daftar produk
     *
     * Mengembalikan semua produk dalam bisnis/cabang pengguna.
     * Scope otomatis dibatasi berdasarkan `business_id` dan `branch_id` dari token login.
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": "uuid",
     *       "name": "Nasi Kucing",
     *       "price": 5000,
     *       "category_id": "uuid",
     *       "created_at": "2024-01-01T00:00:00+00:00"
     *     }
     *   ]
     * }
     */
    public function index()
    {
        //

        return response()->json([]);
    }

    /**
     * Buat produk baru
     *
     * @response 201 {"data": {"id": "uuid", "name": "Nasi Kucing", "price": 5000, "category_id": "uuid"}}
     */
    public function store(Request $request)
    {
        //

        return response()->json([]);
    }

    /**
     * Detail produk
     *
     * @response 200 {"data": {"id": "uuid", "name": "Nasi Kucing", "price": 5000, "category_id": "uuid"}}
     * @response 404 {"message": "Not found."}
     */
    public function show($id)
    {
        //

        return response()->json([]);
    }

    /**
     * Perbarui produk
     *
     * @response 200 {"data": {"id": "uuid", "name": "Nasi Kucing Updated", "price": 6000}}
     * @response 404 {"message": "Not found."}
     */
    public function update(Request $request, $id)
    {
        //

        return response()->json([]);
    }

    /**
     * Hapus produk
     *
     * @response 200 {"message": "Produk berhasil dihapus."}
     * @response 404 {"message": "Not found."}
     */
    public function destroy($id)
    {
        //

        return response()->json([]);
    }
}

