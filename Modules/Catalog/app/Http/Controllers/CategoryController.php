<?php

namespace Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * @tags Catalog — Kategori
 */
class CategoryController extends Controller
{
    /**
     * Daftar kategori
     *
     * Mengembalikan semua kategori produk dalam bisnis pengguna.
     *
     * @response 200 {"data": [{"id": "uuid", "name": "Minuman", "created_at": "2024-01-01T00:00:00+00:00"}]}
     */
    public function index()
    {
        //

        return response()->json([]);
    }

    /**
     * Buat kategori baru
     *
     * @response 201 {"data": {"id": "uuid", "name": "Minuman"}}
     */
    public function store(Request $request)
    {
        //

        return response()->json([]);
    }

    /**
     * Detail kategori
     *
     * @response 200 {"data": {"id": "uuid", "name": "Minuman"}}
     * @response 404 {"message": "Not found."}
     */
    public function show($id)
    {
        //

        return response()->json([]);
    }

    /**
     * Perbarui kategori
     *
     * @response 200 {"data": {"id": "uuid", "name": "Minuman Updated"}}
     * @response 404 {"message": "Not found."}
     */
    public function update(Request $request, $id)
    {
        //

        return response()->json([]);
    }

    /**
     * Hapus kategori
     *
     * @response 200 {"message": "Kategori berhasil dihapus."}
     * @response 404 {"message": "Not found."}
     */
    public function destroy($id)
    {
        //

        return response()->json([]);
    }
}

