<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Catalog\Models\Product;
use Modules\Sales\Services\PlateScanService;

class PlateScanController extends Controller
{
    /**
     * Scan a plate of food from a camera capture or uploaded image and
     * return catalog products matched to the detected items.
     */
    public function scan(Request $request, PlateScanService $scanner)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,jpg,png,webp,gif|max:8192',
        ]);

        $file = $request->file('image');
        $imagePath = $file->store('plate-scans');

        // Tenantable's global scope limits this to the authenticated user's business.
        $products = Product::query()->get(['id', 'name', 'price']);

        // Demo mode: no API key configured — return a mock result from the
        // real catalog so the feature is fully demoable at zero cost.
        if (blank(config('services.anthropic.key'))) {
            return response()->json($this->demoResponse($products, $imagePath));
        }

        try {
            $result = $scanner->scan(
                base64_encode($file->getContent()),
                $file->getMimeType(),
                $products,
            );
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Plate scan failed',
                'error' => $e->getMessage(),
            ], 502);
        }

        $productsById = $products->keyBy('id');
        $matched = [];
        $unmatched = [];

        foreach ($result['items'] as $item) {
            $product = $item['product_id'] !== null ? $productsById->get($item['product_id']) : null;
            $quantity = max(1, (int) $item['quantity']);

            if ($product) {
                $matched[] = [
                    'detected_name' => $item['detected_name'],
                    'quantity' => $quantity,
                    'confidence' => $item['confidence'],
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => (float) $product->price,
                    ],
                ];
            } else {
                $unmatched[] = [
                    'detected_name' => $item['detected_name'],
                    'quantity' => $quantity,
                ];
            }
        }

        return response()->json([
            'items' => $matched,
            'unmatched' => $unmatched,
            'image_path' => $imagePath,
        ]);
    }

    /**
     * Mock scan result used when no Anthropic API key is configured.
     */
    private function demoResponse($products, string $imagePath)
    {
        $items = $products->take(2)->values()->map(fn ($product, $index) => [
            'detected_name' => $product->name,
            'quantity' => $index + 1,
            'confidence' => $index === 0 ? 'high' : 'medium',
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
            ],
        ])->all();

        return [
            'items' => $items,
            'unmatched' => [['detected_name' => 'Kerupuk', 'quantity' => 1]],
            'image_path' => $imagePath,
            'demo' => true,
        ];
    }
}
