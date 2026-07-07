<?php

namespace Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Models\Category;

/**
 * @tags Catalog — Kategori
 */
class CategoryController extends Controller
{
    /**
     * Daftar kategori
     *
     * Mengembalikan semua kategori produk dalam bisnis pengguna.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Category::withCount('products')->orderBy('name')->get(),
        ]);
    }

    /**
     * Buat kategori baru
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $category = Category::create($data);

        return response()->json([
            'message' => 'Kategori berhasil dibuat.',
            'data'    => $category,
        ], 201);
    }

    /**
     * Detail kategori
     */
    public function show(string $id): JsonResponse
    {
        $category = Category::withCount('products')->findOrFail($id);

        return response()->json(['data' => $category]);
    }

    /**
     * Perbarui kategori
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $category->update($data);

        return response()->json([
            'message' => 'Kategori berhasil diperbarui.',
            'data'    => $category,
        ]);
    }

    /**
     * Hapus kategori
     */
    public function destroy(string $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json(['message' => 'Kategori berhasil dihapus.']);
    }
}

