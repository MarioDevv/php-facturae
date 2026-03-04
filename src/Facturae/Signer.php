<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Facturae;

use MarioDevv\Rex\Facturae\Signer\Pkcs12Signer;

/**
 * Convenience facade for creating signers.
 *
 *     ->sign(Signer::pfx('cert.pfx', 'password'))
 *     ->sign(Signer::pfx('cert.pfx', 'password')->timestamp('https://freetsa.org/tsr'))
 *     ->sign(Signer::pem('cert.pem', 'key.pem'))
 */
final class Signer
{
    public static function pfx(string $path, ?string $passphrase = null): Pkcs12Signer
    {
        return Pkcs12Signer::pfx($path, $passphrase);
    }

    public static function pem(string $certPath, string $keyPath, ?string $passphrase = null): Pkcs12Signer
    {
        return Pkcs12Signer::pem($certPath, $keyPath, $passphrase);
    }
}
