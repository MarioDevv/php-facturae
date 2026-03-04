<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Verifactu;

use DateTimeImmutable;
use MarioDevv\Rex\Verifactu\Client\AeatClient;
use MarioDevv\Rex\Verifactu\Client\AeatEndpoint;
use MarioDevv\Rex\Verifactu\Client\SubmitResponse;
use MarioDevv\Rex\Verifactu\Exceptions\VerifactuException;

/**
 * Fachada pública del cliente VERI*FACTU.
 *
 * @example
 * // Con certificado PFX
 * $client = VerifactuClient::staging($system, 'cert.pfx', 'password')
 *     ->obligado('B76123456', 'Atlantic Systems S.L.');
 *
 * $response = $client->submit($record);
 * if ($response->accepted()) {
 *     echo $response->csv();
 * }
 *
 * // Sin certificado (staging anónimo para pruebas de conectividad)
 * $client = VerifactuClient::stagingAnonymous($system)
 *     ->obligado('B76123456', 'Atlantic Systems S.L.');
 */
final class VerifactuClient
{
    private string $obligadoNif    = '';
    private string $obligadoNombre = '';

    private function __construct(
        private readonly AeatClient $http,
        private readonly ComputerSystem $system,
    ) {}

    // -------------------------------------------------------------------------
    // Named constructors
    // -------------------------------------------------------------------------

    /**
     * Cliente de producción con certificado PFX/P12.
     */
    public static function production(
        ComputerSystem $system,
        string $pfxPath,
        string $password,
    ): self {
        return new self(
            http: AeatClient::withPfx(AeatEndpoint::Production, $pfxPath, $password),
            system: $system,
        );
    }

    /**
     * Cliente de staging (entorno de pruebas) con certificado PFX/P12.
     */
    public static function staging(
        ComputerSystem $system,
        string $pfxPath,
        string $password,
    ): self {
        return new self(
            http: AeatClient::withPfx(AeatEndpoint::Staging, $pfxPath, $password),
            system: $system,
        );
    }

    /**
     * Cliente de staging con certificados PEM separados.
     */
    public static function stagingPem(
        ComputerSystem $system,
        string $pemCert,
        string $pemKey,
    ): self {
        return new self(
            http: AeatClient::withPem(AeatEndpoint::Staging, $pemCert, $pemKey),
            system: $system,
        );
    }

    /**
     * Cliente de staging sin certificado.
     * Útil para probar la generación de XML y la conectividad básica.
     * La AEAT rechazará las peticiones sin certificado, pero el HTTP llega.
     */
    public static function stagingAnonymous(ComputerSystem $system): self
    {
        return new self(
            http: AeatClient::withoutCert(AeatEndpoint::Staging),
            system: $system,
        );
    }

    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    /**
     * Datos del obligado tributario que firma el suministro.
     * Normalmente es el mismo que el emisor de las facturas.
     */
    public function obligado(string $nif, string $nombre): self
    {
        $clone = clone $this;
        $clone->obligadoNif    = $nif;
        $clone->obligadoNombre = $nombre;
        return $clone;
    }

    // -------------------------------------------------------------------------
    // Submit
    // -------------------------------------------------------------------------

    /**
     * Envía un registro de facturación a la AEAT.
     */
    public function submit(RegistrationRecord $record): SubmitResponse
    {
        $this->assertObligado();
        return $this->http->submit($record, $this->system, $this->obligadoNif, $this->obligadoNombre);
    }

    /**
     * Envía un lote de registros a la AEAT en una sola petición HTTP.
     *
     * @param RegistrationRecord[] $records Máximo 1000 registros por envío
     */
    public function submitBatch(array $records): SubmitResponse
    {
        $this->assertObligado();
        return $this->http->submitBatch($records, $this->system, $this->obligadoNif, $this->obligadoNombre);
    }

    /**
     * Consulta el estado de un registro en la AEAT.
     */
    public function query(
        string $issuerNif,
        string $invoiceNumber,
        DateTimeImmutable $issueDate,
    ): SubmitResponse {
        $this->assertObligado();
        return $this->http->query($issuerNif, $invoiceNumber, $issueDate, $this->obligadoNif);
    }

    // -------------------------------------------------------------------------

    private function assertObligado(): void
    {
        if ($this->obligadoNif === '') {
            throw new VerifactuException(
                'Debes configurar el obligado tributario con ->obligado(nif, nombre) antes de enviar.'
            );
        }
    }
}
