<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Facturae\Entities;

use MarioDevv\Rex\Facturae\Enums\Tax;

final readonly class TaxBreakdown
{
    public function __construct(
        public Tax   $type,
        public float $rate,
        public bool  $isWithholding = false,
    ) {}
}
