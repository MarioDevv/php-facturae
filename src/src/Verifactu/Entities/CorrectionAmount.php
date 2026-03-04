<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Verifactu\Entities;

/**
 * Importe de la rectificación (base y cuota rectificada).
 * Solo aplica cuando TipoRectificativa = 'S' (por sustitución).
 */
final class CorrectionAmount
{
    public function __construct(
        private readonly float $correctedBase,
        private readonly float $correctedTax,
        private readonly float $correctedSurcharge = 0.0,
    ) {}

    public function getCorrectedBase(): float { return $this->correctedBase; }
    public function getCorrectedTax(): float { return $this->correctedTax; }
    public function getCorrectedSurcharge(): float { return $this->correctedSurcharge; }
}
