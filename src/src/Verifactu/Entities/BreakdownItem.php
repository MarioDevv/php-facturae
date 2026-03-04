<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Verifactu\Entities;

use MarioDevv\Rex\Verifactu\Enums\ExemptionCause;
use MarioDevv\Rex\Verifactu\Enums\OperationType;
use MarioDevv\Rex\Verifactu\Enums\RegimeType;
use MarioDevv\Rex\Verifactu\Enums\TaxType;

/**
 * Detalle de un bloque de desglose de IVA/IGIC/IPSI.
 */
final class BreakdownItem
{
    private TaxType $taxType = TaxType::IVA;
    private OperationType $operationType = OperationType::SubjectNotExempt;
    private ?ExemptionCause $exemptionCause = null;
    private float $taxRate = 0.0;
    private float $baseAmount = 0.0;
    private float $taxAmount = 0.0;
    private float $surchargeRate = 0.0;
    private float $surchargeAmount = 0.0;
    private float $exemptBaseAmount = 0.0;

    private function __construct(
        private readonly RegimeType $regimeType,
    ) {}

    public static function create(RegimeType $regimeType = RegimeType::General): self
    {
        return new self($regimeType);
    }

    public function taxType(TaxType $type): self
    {
        $clone = clone $this;
        $clone->taxType = $type;
        return $clone;
    }

    public function operationType(OperationType $type): self
    {
        $clone = clone $this;
        $clone->operationType = $type;
        return $clone;
    }

    public function exemptionCause(ExemptionCause $cause, float $baseAmount = 0.0): self
    {
        $clone = clone $this;
        $clone->operationType = OperationType::Exempt;
        $clone->exemptionCause = $cause;
        $clone->exemptBaseAmount = $baseAmount;
        return $clone;
    }

    public function rates(float $taxRate, float $baseAmount, float $taxAmount): self
    {
        $clone = clone $this;
        $clone->taxRate = $taxRate;
        $clone->baseAmount = $baseAmount;
        $clone->taxAmount = $taxAmount;
        return $clone;
    }

    public function surcharge(float $surchargeRate, float $surchargeAmount): self
    {
        $clone = clone $this;
        $clone->surchargeRate = $surchargeRate;
        $clone->surchargeAmount = $surchargeAmount;
        return $clone;
    }

    public function getRegimeType(): RegimeType { return $this->regimeType; }
    public function getTaxType(): TaxType { return $this->taxType; }
    public function getOperationType(): OperationType { return $this->operationType; }
    public function getExemptionCause(): ?ExemptionCause { return $this->exemptionCause; }
    public function getTaxRate(): float { return $this->taxRate; }
    public function getBaseAmount(): float { return $this->baseAmount; }
    public function getTaxAmount(): float { return $this->taxAmount; }
    public function getSurchargeRate(): float { return $this->surchargeRate; }
    public function getSurchargeAmount(): float { return $this->surchargeAmount; }
    public function getExemptBaseAmount(): float { return $this->exemptBaseAmount; }

    public function isExempt(): bool
    {
        return $this->operationType === OperationType::Exempt;
    }
}
