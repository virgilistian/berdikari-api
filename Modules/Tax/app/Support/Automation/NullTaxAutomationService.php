<?php

namespace Modules\Tax\Support\Automation;

use Modules\Tax\Contracts\TaxAutomationServiceInterface;
use Modules\Tax\DTO\TaxAutomationResult;
use Modules\Tax\Models\TaxReport;

/**
 * Default no-op binding for TaxAutomationServiceInterface. Proves the
 * generation engine is decoupled from automation without building a real
 * gov-portal integration. A future implementation swaps in via the same
 * binding in TaxServiceProvider — no other file changes.
 */
class NullTaxAutomationService implements TaxAutomationServiceInterface
{
    public function login(): bool
    {
        return false;
    }

    public function submit(TaxReport $report): TaxAutomationResult
    {
        return new TaxAutomationResult(
            success: false,
            message: 'Automation belum tersedia untuk jenis usaha ini.',
        );
    }
}
