<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Facturae;

use MarioDevv\Rex\Facturae\Signer\Pkcs12Signer;

/**
 * Convenience facade for creating signers.
 *
 *     ->sign(Signer::pfx('cert.pfx', 'password'))
 */
final class Signer
{
    public static function pfx(string $path, ?string $passphrase = null): Pkcs12Signer
    {
        return Pkcs12Signer::pfx($path, $passphrase);
    }
}
