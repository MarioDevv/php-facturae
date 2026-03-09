<?php

declare(strict_types=1);

namespace PhpFacturae\Exceptions;

use InvalidArgumentException;

final class InvalidPostalCodeException extends InvalidArgumentException
{
    public static function invalid(string $value): self
    {
        return new self("Invalid postal code: '{$value}'. Expected 5 digits.");
    }
}
