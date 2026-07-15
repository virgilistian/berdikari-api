<?php

namespace Modules\Tax\DTO;

class TaxAutomationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $referenceNumber = null,
        public readonly ?string $message = null,
    ) {
    }
}
