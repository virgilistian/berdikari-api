<?php

namespace Modules\Sales\Services;

use Anthropic\Client;
use Illuminate\Support\Collection;
use RuntimeException;

class PlateScanService
{
    /**
     * Structured output schema: every detected item is either matched to a
     * catalog product (product_id) or left unmatched (product_id = null).
     */
    private const OUTPUT_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'items' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'detected_name' => ['type' => 'string'],
                        'quantity' => ['type' => 'integer'],
                        'product_id' => ['type' => ['string', 'null']],
                        'confidence' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                    ],
                    'required' => ['detected_name', 'quantity', 'product_id', 'confidence'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['items'],
        'additionalProperties' => false,
    ];

    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a point-of-sale assistant for an Indonesian food stall. You are shown a photo of a plate (or tray/table) of food and drinks, plus the stall's product catalog as JSON.

Identify every distinct food or drink item visible in the photo and count how many of each there are. Match each detected item to the catalog product it most likely corresponds to and return that product's id as product_id. Only match when the catalog product plausibly describes the detected item; if nothing in the catalog fits, return product_id as null. Use Indonesian food knowledge (e.g. sate usus vs sate ayam, tempe vs tahu) when telling similar items apart.

quantity is the count of that item on the plate (minimum 1). confidence reflects how sure you are of the match: "high" when the item clearly is that product, "medium" when likely, "low" when it is a guess.
PROMPT;

    /**
     * @param  Collection<int, \Modules\Catalog\Models\Product>  $products
     * @return array{items: list<array{detected_name: string, quantity: int, product_id: ?string, confidence: string}>}
     */
    public function scan(string $imageBase64, string $mediaType, Collection $products): array
    {
        $client = new Client(apiKey: config('services.anthropic.key'));

        $catalog = $products->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'price' => (float) $p->price,
        ])->values()->all();

        $message = $client->messages->create(
            model: 'claude-opus-4-8',
            maxTokens: 4096,
            thinking: ['type' => 'adaptive'],
            system: self::SYSTEM_PROMPT,
            messages: [[
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'image',
                        'source' => [
                            'type' => 'base64',
                            'mediaType' => $mediaType,
                            'data' => $imageBase64,
                        ],
                    ],
                    [
                        'type' => 'text',
                        'text' => "Product catalog:\n".json_encode($catalog, JSON_UNESCAPED_UNICODE)
                            ."\n\nIdentify the items on this plate and match them to the catalog.",
                    ],
                ],
            ]],
            outputConfig: ['format' => ['type' => 'json_schema', 'schema' => self::OUTPUT_SCHEMA]],
        );

        if ($message->stopReason === 'refusal') {
            throw new RuntimeException('The image could not be analyzed.');
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $decoded = json_decode($block->text, true);

                if (is_array($decoded) && array_key_exists('items', $decoded)) {
                    return $decoded;
                }
            }
        }

        throw new RuntimeException('No recognition result was returned.');
    }
}
