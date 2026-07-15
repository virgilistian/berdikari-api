<?php

namespace Modules\Tax\Contracts;

use Modules\Tax\DTO\CalendarContext;
use Modules\Tax\DTO\GeneratedReportDraft;
use Modules\Tax\DTO\TaxGenerationConfig;

/**
 * One implementation per business type (Restaurant, SwimmingPool, ...).
 * Core services (TaxGenerationService, TaxNormalizationService, TaxPdfService)
 * only ever talk to this interface via TaxGeneratorRegistry — never a
 * business-type conditional — so a new type plugs in without touching them.
 */
interface TaxGeneratorInterface
{
    /**
     * Registry key, e.g. 'restaurant' | 'swimming_pool'.
     */
    public static function key(): string;

    /**
     * Bahasa Indonesia label for the UI, e.g. 'Rumah Makan'.
     */
    public static function label(): string;

    /**
     * Randomly generate a full month of entries honoring the calendar
     * (weekday/weekend/holiday) and config rules. Does not enforce the
     * monthly cap — TaxNormalizationService does that afterwards.
     */
    public function generate(CalendarContext $calendar, TaxGenerationConfig $config, ?int $seed = null): GeneratedReportDraft;

    /**
     * Recompute derived fields (sales/tax, and for pool: sales from qty*price)
     * from already-set driving values. Never re-randomizes — used both by
     * TaxNormalizationService (after scaling) and manual-edit recompute.
     *
     * @param  array[]  $entries  entry rows (see GeneratedReportDraft)
     */
    public function recompute(array $entries, TaxGenerationConfig $config): GeneratedReportDraft;

    /**
     * Blade view name for the PDF, e.g. 'tax::pdf.restaurant'.
     */
    public function pdfView(): string;

    /**
     * Column metadata driving the frontend's generic editable table.
     * Each item: ['key' => string, 'label' => string, 'editable' => bool, 'type' => 'number'|'text'].
     */
    public function entryColumns(): array;
}
