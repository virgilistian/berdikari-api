<?php

namespace Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Modules\Catalog\Models\Product;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * Unggah / ganti foto produk
     *
     * Menerima foto dari kamera atau galeri. Gambar otomatis diubah
     * ukurannya dan dikompres di server (terlepas dari kompresi di aplikasi)
     * agar ukuran penyimpanan tetap kecil dan aman untuk kuota server.
     */
    public function uploadImage(Request $request, string $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $request->validate([
            // 10 MB raw ceiling: generous enough for an uncompressed camera
            // photo that slipped past client-side compression, small enough
            // to keep a single upload from exhausting server memory/disk.
            'file' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:10240'],
        ], [
            'file.required' => 'Foto produk wajib dipilih.',
            'file.image'    => 'Berkas harus berupa gambar.',
            'file.mimes'    => 'Format gambar harus PNG, JPG, atau WEBP.',
            'file.max'      => 'Ukuran foto maksimal 10 MB.',
        ]);

        $file = $request->file('file');

        $manager = new ImageManager(new Driver());
        // Downscale to a grid/detail-friendly width and force-encode as
        // JPEG at quality 75 — the final stored photo is typically well
        // under 100 KB regardless of the original camera resolution/format.
        $image = $manager->read($file->getRealPath())->scaleDown(width: 600);
        $encoded = $image->toJpeg(quality: 75);

        $disk = 's3';
        $path = "catalog/products/{$product->business_id}/{$product->id}.jpg";

        if ($product->photo_path && Storage::disk($product->photo_disk ?? $disk)->exists($product->photo_path)) {
            Storage::disk($product->photo_disk ?? $disk)->delete($product->photo_path);
        }

        Storage::disk($disk)->put($path, (string) $encoded);

        $product->update([
            'photo_disk'      => $disk,
            'photo_path'      => $path,
            'photo_mime_type' => $encoded->mimetype(),
        ]);

        return response()->json([
            'message' => 'Foto produk berhasil diunggah.',
            'data'    => $product->fresh()->load('category'),
        ]);
    }

    /**
     * Tampilkan foto produk
     */
    public function showImage(string $id): StreamedResponse
    {
        $product = Product::findOrFail($id);

        if (! $product->photo_path || ! Storage::disk($product->photo_disk)->exists($product->photo_path)) {
            abort(404, 'Produk ini belum memiliki foto.');
        }

        return Storage::disk($product->photo_disk)->response($product->photo_path, null, [
            'Content-Type'  => $product->photo_mime_type ?? 'image/jpeg',
            // Private — served behind auth:sanctum, not for shared/proxy caches.
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Hapus foto produk
     */
    public function deleteImage(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        if ($product->photo_path && Storage::disk($product->photo_disk)->exists($product->photo_path)) {
            Storage::disk($product->photo_disk)->delete($product->photo_path);
        }

        $product->update(['photo_disk' => null, 'photo_path' => null, 'photo_mime_type' => null]);

        return response()->json(['message' => 'Foto produk berhasil dihapus.']);
    }
}

