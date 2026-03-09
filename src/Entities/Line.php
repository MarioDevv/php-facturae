<?php

declare(strict_types=1);

namespace PhpFacturae\Entities;

use PhpFacturae\Enums\SpecialTaxableEvent;
use PhpFacturae\Enums\UnitOfMeasure;

final class Line
{
    /**
     * @param string               $description
     * @param float                $quantity
     * @param float                $unitPrice          Precio unitario sin impuestos
     * @param TaxBreakdown[]       $taxes
     * @param string|null          $articleCode
     * @param float|null           $discount           Porcentaje de descuento (0-100)
     * @param string|null          $detailedDescription
     * @param UnitOfMeasure|null   $unitOfMeasure
     * @param SpecialTaxableEvent|null $specialTaxableEvent
     * @param string|null          $specialTaxableEventReason  Motivo de la fiscalidad especial
     */
    public function __construct(
        public readonly string                  $description,
        public readonly float                   $quantity,
        public readonly float                   $unitPrice,
        public readonly array                   $taxes = [],
        public readonly ?string                 $articleCode = null,
        public readonly ?float                  $discount = null,
        public readonly ?string                 $detailedDescription = null,
        public readonly ?UnitOfMeasure          $unitOfMeasure = null,
        public readonly ?SpecialTaxableEvent    $specialTaxableEvent = null,
        public readonly ?string                 $specialTaxableEventReason = null,
    ) {}

    /**
     * Importe bruto (cantidad * precio - descuento).
     */
    public function grossAmount(): float
    {
        $total = $this->quantity * $this->unitPrice;

        if ($this->discount !== null && $this->discount > 0) {
            $total -= round($total * $this->discount / 100, 2);
        }

        return round($total, 2);
    }
}
