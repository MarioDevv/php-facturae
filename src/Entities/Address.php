<?php

declare(strict_types=1);

namespace PhpFacturae\Entities;

use PhpFacturae\Exceptions\InvalidPostalCodeException;

final readonly class Address
{
    public function __construct(
        public string $street,
        public string $postalCode,
        public string $town,
        public string $province,
        public string $countryCode = 'ESP',
    ) {
        if ($this->countryCode === 'ESP' && !preg_match('/^\d{5}$/', $this->postalCode)) {
            throw InvalidPostalCodeException::invalid($this->postalCode);
        }
    }
}
