<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Verifactu\Exceptions;

/**
 * Error de comunicación con el servicio VERI*FACTU de la AEAT.
 */
class AeatCommunicationException extends VerifactuException
{
    public static function networkError(string $detail): self
    {
        return new self("Error de red: {$detail}");
    }

    public static function httpError(int $status, string $body): self
    {
        return new self("La AEAT respondió con HTTP {$status}: " . substr($body, 0, 300));
    }

    public static function certificateError(string $detail): self
    {
        return new self("Error de certificado: {$detail}");
    }
}
