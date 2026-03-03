<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Facturae\Signer;

use RuntimeException;

final class Pkcs12Signer implements InvoiceSigner
{
    private ?string $timestampUrl = null;

    private function __construct(
        private readonly string  $certPath,
        private readonly ?string $passphrase,
    ) {
        if (!file_exists($certPath)) {
            throw new RuntimeException("Certificate file not found: {$certPath}");
        }
    }

    public static function pfx(string $path, ?string $passphrase = null): self
    {
        return new self($path, $passphrase);
    }

    public function withTimestamp(string $url): self
    {
        $clone = clone $this;
        $clone->timestampUrl = $url;
        return $clone;
    }

    public function sign(string $xml): string
    {
        // TODO: Implement XAdES-EPES signing
        throw new RuntimeException('XAdES signing not yet implemented.');
    }
}
