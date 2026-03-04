<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Facturae\Signer;

interface InvoiceSigner
{
    /**
     * Sign a FacturaE XML string and return the signed XML.
     */
    public function sign(string $xml): string;
}
