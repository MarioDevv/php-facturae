<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Verifactu\Exceptions;

/**
 * El registro de facturación contiene datos inválidos o incompletos.
 */
class InvalidRecordException extends VerifactuException
{
    public static function missingField(string $field): self
    {
        return new self("Campo obligatorio no definido: {$field}.");
    }

    public static function correctionTypeRequiresInvoiceType(string $invoiceType): self
    {
        return new self(
            "El tipo de factura '{$invoiceType}' es una rectificativa pero no se ha especificado TipoRectificativa."
        );
    }

    public static function rectifiedInvoicesRequiredForSubstitution(): self
    {
        return new self(
            'Las facturas rectificativas por sustitución deben indicar las facturas rectificadas (addRectifiedInvoice).'
        );
    }
}
