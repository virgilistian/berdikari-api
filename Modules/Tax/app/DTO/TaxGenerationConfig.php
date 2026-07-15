<?php

namespace Modules\Tax\DTO;

/**
 * Merged view of config('tax.*') defaults overridden by a business's
 * TaxBusinessProfile.config_overrides — generators/services never read
 * config()/the profile directly, only this DTO.
 */
class TaxGenerationConfig
{
    public function __construct(
        private readonly array $values,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->values, $key, $default);
    }

    public function taxPercentage(): float
    {
        return (float) $this->get('tax_percentage', 0.10);
    }

    public function monthlyCap(bool $hasHoliday): float
    {
        return (float) $this->get($hasHoliday ? 'monthly_cap.with_holiday' : 'monthly_cap.without_holiday');
    }

    public function toArray(): array
    {
        return $this->values;
    }
}
