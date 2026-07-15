<?php

namespace Modules\Tax\Services;

use Modules\Tax\Contracts\TaxGeneratorInterface;
use Modules\Tax\DTO\GeneratedReportDraft;
use Modules\Tax\DTO\TaxGenerationConfig;

/**
 * Scales a generated draft down until its monthly tax total respects the cap.
 * Generic across business types: it only ever touches the generator's
 * declared `drivingValueKey()` field and calls `recompute()` to re-derive
 * sales/tax — it never reads/writes a Restaurant- or Pool-specific field
 * directly.
 */
class TaxNormalizationService
{
    private const MAX_TRIM_ITERATIONS = 2000;

    public function normalize(
        TaxGeneratorInterface $generator,
        GeneratedReportDraft $draft,
        float $cap,
        TaxGenerationConfig $config,
    ): GeneratedReportDraft {
        if ($draft->totalTax() <= $cap) {
            return $draft;
        }

        $key = $generator->drivingValueKey();
        $entries = $draft->entries;
        $totalTax = $draft->totalTax();

        $scaleFactor = $totalTax > 0 ? $cap / $totalTax : 1.0;

        foreach ($entries as &$entry) {
            $value = (float) ($entry[$key] ?? 0);
            if ($value > 0) {
                $entry[$key] = (int) round($value * $scaleFactor);
            }
        }
        unset($entry);

        $draft = $generator->recompute($entries, $config);

        // Rounding can still leave the sum marginally over cap — trim the
        // largest non-zero driving value by one unit at a time until compliant.
        $step = $generator->drivingValueStep();
        $iterations = 0;
        while ($draft->totalTax() > $cap && $iterations < self::MAX_TRIM_ITERATIONS) {
            $entries = $draft->entries;
            $largestIndex = null;
            $largestValue = 0;

            foreach ($entries as $index => $entry) {
                $value = (float) ($entry[$key] ?? 0);
                if ($value > $largestValue) {
                    $largestValue = $value;
                    $largestIndex = $index;
                }
            }

            if ($largestIndex === null) {
                break; // every driving value is already zero; cap cannot be satisfied further
            }

            $entries[$largestIndex][$key] = max(0, (int) $entries[$largestIndex][$key] - $step);
            $draft = $generator->recompute($entries, $config);
            $iterations++;
        }

        $draft->wasNormalized = true;

        return $draft;
    }
}
