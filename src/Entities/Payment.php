<?php

declare(strict_types=1);

namespace PhpFacturae\Entities;

use DateTimeImmutable;
use PhpFacturae\Enums\PaymentMethod;

final class Payment
{
    public function __construct(
        public readonly PaymentMethod      $method,
        public readonly ?DateTimeImmutable $dueDate = null,
        public readonly ?float             $amount = null,
        public readonly ?string            $iban = null,
        public readonly ?string            $bic = null,
        public readonly ?int               $installmentIndex = null,
        public readonly ?int               $totalInstallments = null,
    )
    {
    }

    public function isSplitPayment(): bool
    {
        return $this->totalInstallments !== null && $this->totalInstallments > 1;
    }
}
