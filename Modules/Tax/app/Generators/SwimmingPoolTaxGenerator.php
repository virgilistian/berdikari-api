<?php

namespace Modules\Tax\Generators;

use Modules\Tax\DTO\DayInfo;
use Modules\Tax\DTO\GeneratedReportDraft;
use Modules\Tax\DTO\TaxGenerationConfig;

class SwimmingPoolTaxGenerator extends AbstractTaxGenerator
{
    public static function key(): string
    {
        return 'swimming_pool';
    }

    public static function label(): string
    {
        return 'Kolam Renang';
    }

    public function drivingValueKey(): string
    {
        return 'ticket_qty';
    }

    protected function driveValueRange(TaxGenerationConfig $config): array
    {
        return [
            (int) $config->get('swimming_pool.qty_min', 0),
            (int) $config->get('swimming_pool.qty_max', 120),
        ];
    }

    private function priceFor(DayInfo $day, TaxGenerationConfig $config): float
    {
        // National holidays use the weekend rate per spec.
        return ($day->isWeekend || $day->isHoliday)
            ? (float) $config->get('swimming_pool.weekend_price', 35000)
            : (float) $config->get('swimming_pool.weekday_price', 25000);
    }

    protected function buildEntryFromValue(DayInfo $day, float $value, TaxGenerationConfig $config): array
    {
        $qty = (int) round($value);
        $price = $this->priceFor($day, $config);
        $sales = round($qty * $price);
        $tax = round($sales * $config->taxPercentage());

        return [
            'day_number' => $day->dayNumber,
            'weekday_name' => $day->weekdayName,
            'is_weekend' => $day->isWeekend,
            'is_holiday' => $day->isHoliday,
            'holiday_name' => $day->holidayName,
            'ticket_qty' => $qty,
            'ticket_price' => $price,
            'extra' => null,
            'sales' => $sales,
            'tax' => $tax,
            'is_manually_edited' => false,
        ];
    }

    public function recompute(array $entries, TaxGenerationConfig $config): GeneratedReportDraft
    {
        foreach ($entries as &$entry) {
            $qty = (int) round((float) ($entry['ticket_qty'] ?? 0));
            $price = (float) ($entry['ticket_price'] ?? 0);
            $sales = round($qty * $price);

            $entry['ticket_qty'] = $qty;
            $entry['ticket_price'] = $price;
            $entry['sales'] = $sales;
            $entry['tax'] = round($sales * $config->taxPercentage());
        }
        unset($entry);

        return new GeneratedReportDraft($entries);
    }

    public function pdfView(): string
    {
        return 'tax::pdf.swimming_pool';
    }

    public function entryColumns(): array
    {
        return [
            ['key' => 'day_number', 'label' => 'No.', 'editable' => false, 'type' => 'text'],
            ['key' => 'weekday_name', 'label' => 'Hari', 'editable' => false, 'type' => 'text'],
            ['key' => 'is_holiday', 'label' => 'Libur Nasional', 'editable' => false, 'type' => 'boolean'],
            ['key' => 'ticket_qty', 'label' => 'Jumlah Tiket', 'editable' => true, 'type' => 'number'],
            ['key' => 'ticket_price', 'label' => 'Harga Tiket', 'editable' => true, 'type' => 'number'],
            ['key' => 'sales', 'label' => 'Hasil Penjualan', 'editable' => false, 'type' => 'number'],
            ['key' => 'tax', 'label' => 'Pajak 10%', 'editable' => false, 'type' => 'number'],
        ];
    }
}
