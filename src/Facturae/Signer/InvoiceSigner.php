<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Facturae\Signer;

interface InvoiceSigner
{
    public function sign(string $xml): string;
}
