<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Facturae\Entities;

use MarioDevv\Rex\Facturae\Enums\Tax;

final class TaxBreakdown
{
    public function __construct(
        public readonly Tax    $type,
        public readonly float  $rate,
        public readonly bool   $isWithholding = false,
        public readonly ?float $surchargeRate = null,
    )
    {
    }
}
