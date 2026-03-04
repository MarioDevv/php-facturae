<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Facturae\Entities;

use MarioDevv\Rex\Facturae\Enums\Tax;

final class TaxBreakdown
{
    public readonly bool $isWithholding;

    /**
     * @param Tax        $type          Tipo de impuesto
     * @param float      $rate          Porcentaje del impuesto
     * @param bool|null  $isWithholding null = usar defecto del tipo (IRPF/IRNR = retenido, resto = repercutido)
     * @param float|null $surchargeRate Porcentaje de recargo de equivalencia (solo IVA)
     */
    public function __construct(
        public readonly Tax    $type,
        public readonly float  $rate,
        ?bool                  $isWithholding = null,
        public readonly ?float $surchargeRate = null,
    ) {
        $this->isWithholding = $isWithholding ?? $type->isWithheldByDefault();
    }
}
