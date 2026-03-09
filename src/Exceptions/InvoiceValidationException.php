<?php

declare(strict_types=1);

namespace PhpFacturae\Exceptions;

use RuntimeException;

final class InvoiceValidationException extends RuntimeException
{
    /** @param string[] $errors */
    public function __construct(
        public readonly array $errors,
    ) {
        parent::__construct('Invoice validation failed: ' . implode(', ', $errors));
    }
}
