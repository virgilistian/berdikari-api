<?php

namespace Modules\Tax\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Modules\Tax\Models\TaxBusinessAsset;

/**
 * @tags Pajak — Tanda Tangan & Stempel
 */
class TaxAssetController extends Controller
{
    private function businessId(Request $request): string
    {
        return Auth::user()?->business_id ?? (string) $request->input('business_id');
    }

    /**
     * Daftar berkas (tanda tangan, stempel) milik bisnis ini
     */
    public function index(Request $request): JsonResponse
    {
        $assets = TaxBusinessAsset::where('business_id', $this->businessId($request))->get();

        return response()->json(['data' => $assets]);
    }

    /**
     * Unggah / ganti tanda tangan atau stempel
     *
     * Gambar otomatis diubah ukurannya (menjaga rasio aspek) dan disimpan;
     * PNG transparan didukung penuh.
     */
    public function store(Request $request, string $type): JsonResponse
    {
        $assetTypes = config('tax.asset_types', []);

        if (! array_key_exists($type, $assetTypes)) {
            return response()->json(['message' => "Jenis berkas \"{$type}\" tidak dikenali."], 422);
        }

        $rules = $assetTypes[$type];

        $request->validate([
            'file' => [
                'required',
                'image',
                'mimes:' . implode(',', $rules['mimes']),
                'max:' . $rules['max_kb'],
            ],
        ]);

        $file = $request->file('file');

        $manager = new ImageManager(new Driver());
        $image = $manager->read($file->getRealPath())->scaleDown(width: $rules['max_width']);
        $encoded = $image->encode(); // auto-detects original format; preserves PNG alpha

        $businessId = $this->businessId($request);
        $disk = config('tax.asset_disk', 's3');
        $extension = $file->getClientOriginalExtension() ?: 'png';
        $path = "tax/business-assets/{$businessId}/{$type}.{$extension}";

        $existing = TaxBusinessAsset::where('business_id', $businessId)->where('type', $type)->first();
        if ($existing && $existing->path !== $path && Storage::disk($existing->disk)->exists($existing->path)) {
            Storage::disk($existing->disk)->delete($existing->path);
        }

        Storage::disk($disk)->put($path, (string) $encoded);

        $asset = TaxBusinessAsset::updateOrCreate(
            ['business_id' => $businessId, 'type' => $type],
            [
                'disk' => $disk,
                'path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $encoded->mimetype(),
                'width' => $image->width(),
                'height' => $image->height(),
            ],
        );

        return response()->json([
            'message' => 'Berkas berhasil diunggah.',
            'data' => $asset,
        ]);
    }

    /**
     * Hapus tanda tangan atau stempel — area terkait dikosongkan di PDF
     */
    public function destroy(Request $request, string $type): JsonResponse
    {
        $asset = TaxBusinessAsset::where('business_id', $this->businessId($request))
            ->where('type', $type)
            ->first();

        if ($asset) {
            if (Storage::disk($asset->disk)->exists($asset->path)) {
                Storage::disk($asset->disk)->delete($asset->path);
            }
            $asset->delete();
        }

        return response()->json(['message' => 'Berkas berhasil dihapus.']);
    }
}
