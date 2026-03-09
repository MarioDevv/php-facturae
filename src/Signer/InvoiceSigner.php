<?php

declare(strict_types=1);

namespace PhpFacturae\Signer;

interface InvoiceSigner
{
    /**
     * Sign a FacturaE XML string and return the signed XML.
     */
    public function sign(string $xml): string;
}
