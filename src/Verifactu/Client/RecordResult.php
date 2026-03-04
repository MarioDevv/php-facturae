<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Verifactu\Client;

/**
 * Resultado de un registro individual dentro de una respuesta de la AEAT.
 */
final class RecordResult
{
    public function __construct(
        private readonly string $invoiceNumber,
        private readonly bool $accepted,
        private readonly ?string $csv,
        private readonly ?string $errorCode,
        private readonly ?string $errorMessage,
        private readonly ?string $rawState,
    ) {}

    public function invoiceNumber(): string { return $this->invoiceNumber; }
    public function accepted(): bool { return $this->accepted; }
    public function csv(): ?string { return $this->csv; }
    public function errorCode(): ?string { return $this->errorCode; }
    public function errorMessage(): ?string { return $this->errorMessage; }
    public function rawState(): ?string { return $this->rawState; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'numero_factura'  => $this->invoiceNumber,
            'aceptado'        => $this->accepted,
            'csv'             => $this->csv,
            'codigo_error'    => $this->errorCode,
            'mensaje_error'   => $this->errorMessage,
            'estado_raw'      => $this->rawState,
        ], fn($v) => $v !== null);
    }
}
