<?php

use Modules\Tax\Generators\RestaurantTaxGenerator;
use Modules\Tax\Generators\SwimmingPoolTaxGenerator;

return [
    'name' => 'Tax',

    // Applied to every day's Sales to derive Tax.
    'tax_percentage' => 0.10,

    // Total monthly tax must never exceed these caps (Rupiah).
    'monthly_cap' => [
        'without_holiday' => 1_000_000,
        'with_holiday' => 1_500_000,
    ],

    // Business-type key => TaxGeneratorInterface implementation.
    // Add an entry here (plus the matching defaults block below) to
    // register a new business type — no other core file needs to change.
    'generators' => [
        'restaurant' => RestaurantTaxGenerator::class,
        'swimming_pool' => SwimmingPoolTaxGenerator::class,
    ],

    'restaurant' => [
        'sales_min' => 200_000,
        'sales_max' => 1_500_000,
        'weekend_multiplier' => 1.4,
        'holiday_multiplier' => 1.6,
        'zero_days_min' => 1,
        'zero_days_max' => 3,
        'min_day_variance' => 0.05,
    ],

    'swimming_pool' => [
        'weekday_price' => 25_000,
        'weekend_price' => 35_000,
        'qty_min' => 0,
        'qty_max' => 120,
        'weekend_multiplier' => 1.5,
        'holiday_multiplier' => 1.7,
        'zero_days_min' => 1,
        'zero_days_max' => 3,
        'min_day_variance' => 0.05,
    ],

    // Allowlist of uploadable business-asset types. Adding a new type here
    // (e.g. 'logo', 'qr_code', 'watermark') is the only step needed to
    // accept and store it — TaxPdfService/TaxAssetController never branch
    // on a hardcoded type list.
    'asset_types' => [
        'signature' => ['max_kb' => 512, 'max_width' => 600, 'mimes' => ['png', 'jpg', 'jpeg']],
        'stamp' => ['max_kb' => 512, 'max_width' => 600, 'mimes' => ['png', 'jpg', 'jpeg']],
    ],

    // Disk used to store business assets (signature/stamp). Points at the
    // already-configured MinIO-backed `s3` disk (see config/filesystems.php).
    'asset_disk' => 's3',
];
