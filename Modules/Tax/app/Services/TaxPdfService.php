<?php

namespace Modules\Tax\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Modules\Tax\DTO\TaxGenerationConfig;
use Modules\Tax\Models\TaxBusinessAsset;
use Modules\Tax\Models\TaxBusinessProfile;
use Modules\Tax\Models\TaxReport;
use Modules\Tax\Support\TaxGeneratorRegistry;

class TaxPdfService
{
    private const MONTH_NAMES = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function __construct(
        private readonly TaxGeneratorRegistry $registry,
    ) {
    }

    public function render(TaxReport $report): Response
    {
        $report->loadMissing('entries');

        $profile = TaxBusinessProfile::query()
            ->where('business_id', $report->business_id)
            ->where('business_type', $report->business_type)
            ->firstOrFail();

        $generator = $this->registry->resolve($report->business_type);
        $config = new TaxGenerationConfig($report->config_snapshot ?? config('tax', []));

        $assets = TaxBusinessAsset::query()
            ->where('business_id', $report->business_id)
            ->whereIn('type', ['signature', 'stamp'])
            ->get()
            ->keyBy('type');

        $data = [
            'report' => $report,
            'profile' => $profile,
            'config' => $config,
            'monthName' => self::MONTH_NAMES[$report->period_month] ?? $report->period_month,
            'keterangan' => 'Laporan Pajak ' . $generator::label(),
            'signatureDataUri' => $assets->get('signature')?->toDataUri(),
            'stampDataUri' => $assets->get('stamp')?->toDataUri(),
        ];

        $report->update([
            'print_count' => $report->print_count + 1,
            'last_printed_at' => now(),
        ]);

        $filename = "pajak-{$report->business_type}-{$report->period_month}-{$report->period_year}.pdf";

        return Pdf::loadView($generator->pdfView(), $data)
            ->setPaper('a4', 'portrait')
            ->stream($filename);
    }
}
