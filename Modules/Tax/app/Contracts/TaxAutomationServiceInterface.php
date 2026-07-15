<?php

namespace Modules\Tax\Contracts;

use Modules\Tax\DTO\TaxAutomationResult;
use Modules\Tax\Models\TaxReport;

/**
 * Future-ready seam for automatically logging into a government tax portal
 * (or another registered application) and submitting a report. Intentionally
 * decoupled from the generation engine: nothing in TaxGenerationService,
 * TaxNormalizationService, or the controllers depends on this interface —
 * only a real implementation (not built yet) would be wired up to call it.
 */
interface TaxAutomationServiceInterface
{
    public function login(): bool;

    public function submit(TaxReport $report): TaxAutomationResult;
}
