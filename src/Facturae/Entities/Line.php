<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Facturae\Entities;

final readonly class Line
{
    /**
     * @param TaxBreakdown[] $taxes
     */
    public function __construct(
        public string  $description,
        public float   $quantity,
        public float   $unitPrice,
        public array   $taxes,
        public ?string $articleCode = null,
        public ?float  $discount = null,
        public ?string $detailedDescription = null,
    ) {}

    public function grossAmount(): float
    {
        $amount = round($this->quantity * $this->unitPrice, 2);

        if ($this->discount !== null) {
            $amount -= round($amount * $this->discount / 100, 2);
        }

        return $amount;
    }
}
