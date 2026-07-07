<?php

namespace Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Models\Product;

/**
 * @tags Catalog — Produk
 */
class ProductController extends Controller
{
    /**
     * Daftar produk
     *
     * Mengembalikan semua produk dalam bisnis pengguna. Scope otomatis
     * dibatasi berdasarkan `business_id` dari token login.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()->with('category');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return response()->json([
            'data' => $query->orderBy('name')->get(),
        ]);
    }

    /**
     * Buat produk baru
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id'    => 'nullable|uuid',
            'name'           => 'required|string|max:255',
            'sku'            => 'nullable|string|max:100',
            'price'          => 'required|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'cost_price'     => 'nullable|numeric|min:0',
            'is_active'      => 'nullable|boolean',
            'description'    => 'nullable|string',
            'image_url'      => 'nullable|string|max:2048',
        ]);

        $data['is_active'] = $data['is_active'] ?? true;

        $product = Product::create($data);

        return response()->json([
            'message' => 'Produk berhasil dibuat.',
            'data'    => $product->load('category'),
        ], 201);
    }

    /**
     * Detail produk
     */
    public function show(string $id): JsonResponse
    {
        $product = Product::with('category')->findOrFail($id);

        return response()->json(['data' => $product]);
    }

    /**
     * Perbarui produk
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $data = $request->validate([
            'category_id'    => 'nullable|uuid',
            'name'           => 'sometimes|required|string|max:255',
            'sku'            => 'nullable|string|max:100',
            'price'          => 'sometimes|required|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'cost_price'     => 'nullable|numeric|min:0',
            'is_active'      => 'nullable|boolean',
            'description'    => 'nullable|string',
            'image_url'      => 'nullable|string|max:2048',
        ]);

        $product->update($data);

        return response()->json([
            'message' => 'Produk berhasil diperbarui.',
            'data'    => $product->load('category'),
        ]);
    }

    /**
     * Hapus produk
     */
    public function destroy(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json(['message' => 'Produk berhasil dihapus.']);
    }
}

