<?php

namespace Modules\Tax\Generators;

use Modules\Tax\DTO\DayInfo;
use Modules\Tax\DTO\GeneratedReportDraft;
use Modules\Tax\DTO\TaxGenerationConfig;

class RestaurantTaxGenerator extends AbstractTaxGenerator
{
    public static function key(): string
    {
        return 'restaurant';
    }

    public static function label(): string
    {
        return 'Rumah Makan';
    }

    public function drivingValueKey(): string
    {
        return 'sales';
    }

    public function drivingValueStep(): int
    {
        return 1000;
    }

    protected function driveValueRange(TaxGenerationConfig $config): array
    {
        return [
            (int) $config->get('restaurant.sales_min', 200000),
            (int) $config->get('restaurant.sales_max', 1500000),
        ];
    }

    /**
     * Rounds to the nearest clean thousand — restaurant sales figures are
     * never displayed/stored as raw rupiah (e.g. 342323), always 342000.
     */
    private function roundToThousand(float $value): int
    {
        return (int) (round($value / 1000) * 1000);
    }

    protected function buildEntryFromValue(DayInfo $day, float $value, TaxGenerationConfig $config): array
    {
        $sales = $this->roundToThousand($value);
        $tax = round($sales * $config->taxPercentage());

        return [
            'day_number' => $day->dayNumber,
            'weekday_name' => $day->weekdayName,
            'is_weekend' => $day->isWeekend,
            'is_holiday' => $day->isHoliday,
            'holiday_name' => $day->holidayName,
            'ticket_qty' => null,
            'ticket_price' => null,
            'extra' => null,
            'sales' => $sales,
            'tax' => $tax,
            'is_manually_edited' => false,
        ];
    }

    public function recompute(array $entries, TaxGenerationConfig $config): GeneratedReportDraft
    {
        foreach ($entries as &$entry) {
            $sales = $this->roundToThousand((float) ($entry['sales'] ?? 0));
            $entry['sales'] = $sales;
            $entry['tax'] = round($sales * $config->taxPercentage());
        }
        unset($entry);

        return new GeneratedReportDraft($entries);
    }

    public function pdfView(): string
    {
        return 'tax::pdf.restaurant';
    }

    public function entryColumns(): array
    {
        return [
            ['key' => 'day_number', 'label' => 'No.', 'editable' => false, 'type' => 'text'],
            ['key' => 'weekday_name', 'label' => 'Hari', 'editable' => false, 'type' => 'text'],
            ['key' => 'sales', 'label' => 'Hasil Penjualan', 'editable' => true, 'type' => 'number'],
            ['key' => 'tax', 'label' => 'Pajak 10%', 'editable' => false, 'type' => 'number'],
        ];
    }
}
