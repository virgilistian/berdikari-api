<?php

namespace Modules\Tax\DTO;

/**
 * A generator's output: one assoc array per calendar day (matching
 * tax_report_entries columns) plus the derived monthly totals.
 */
class GeneratedReportDraft
{
    public bool $wasNormalized = false;

    /**
     * @param  array[]  $entries  each: day_number, weekday_name, is_weekend, is_holiday,
     *                            holiday_name, ticket_qty?, ticket_price?, extra?, sales, tax, is_manually_edited
     */
    public function __construct(
        public array $entries,
    ) {
    }

    public function totalSales(): float
    {
        return round(array_sum(array_column($this->entries, 'sales')), 2);
    }

    public function totalTax(): float
    {
        return round(array_sum(array_column($this->entries, 'tax')), 2);
    }
}
