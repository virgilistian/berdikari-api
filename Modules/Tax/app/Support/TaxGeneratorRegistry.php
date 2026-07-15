<?php

namespace Modules\Tax\Support;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Modules\Tax\Contracts\TaxGeneratorInterface;

/**
 * Resolves a business-type key ('restaurant', 'swimming_pool', ...) to its
 * TaxGeneratorInterface implementation. Every core service goes through this
 * registry — never an `if ($type === ...)` branch — so a new business type
 * is a config('tax.generators') entry, not a code change here.
 */
class TaxGeneratorRegistry
{
    public function __construct(
        private readonly Container $container,
    ) {
    }

    public function resolve(string $businessType): TaxGeneratorInterface
    {
        $class = config("tax.generators.{$businessType}");

        if (! $class || ! is_a($class, TaxGeneratorInterface::class, true)) {
            throw new InvalidArgumentException("Jenis usaha \"{$businessType}\" tidak terdaftar.");
        }

        return $this->container->make($class);
    }

    /**
     * @return array<int, array{key: string, label: string, columns: array}>
     */
    public function all(): array
    {
        $types = [];

        foreach (array_keys(config('tax.generators', [])) as $key) {
            $generator = $this->resolve($key);
            $types[] = [
                'key' => $generator::key(),
                'label' => $generator::label(),
                'columns' => $generator->entryColumns(),
            ];
        }

        return $types;
    }
}
