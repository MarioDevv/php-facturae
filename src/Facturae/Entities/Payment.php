<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Facturae\Entities;

use DateTimeImmutable;
use MarioDevv\Rex\Facturae\Enums\PaymentMethod;

final readonly class Payment
{
    public function __construct(
        public PaymentMethod      $method,
        public ?DateTimeImmutable  $dueDate = null,
        public ?float              $amount = null,
        public ?string             $iban = null,
        public ?string             $bic = null,
    ) {}
}
