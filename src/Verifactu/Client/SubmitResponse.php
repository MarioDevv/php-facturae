<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Verifactu\Client;

use MarioDevv\Rex\Verifactu\Exceptions\VerifactuException;

/**
 * Respuesta parseada del servicio VERI*FACTU de la AEAT.
 *
 * Cubre tanto envíos individuales como por lote.
 * Para envíos individuales, `accepted()` y `csv()` devuelven
 * el resultado del único registro enviado.
 */
final class SubmitResponse
{
    /** @param RecordResult[] $results */
    public function __construct(
        private readonly int $httpStatus,
        private readonly string $rawBody,
        private readonly array $results,
        private readonly ?string $globalErrorCode = null,
        private readonly ?string $globalErrorMessage = null,
        private readonly ?string $globalCsv = null,
        private readonly ?string $tipoRespuesta = null,
    ) {}

    // -------------------------------------------------------------------------
    // Single-record helpers (sugar for the common case)
    // -------------------------------------------------------------------------

    /**
     * ¿La AEAT ha aceptado el registro?
     * Para lotes, devuelve true solo si TODOS fueron aceptados.
     */
    public function accepted(): bool
    {
        if (!empty($this->results)) {
            return array_reduce(
                $this->results,
                fn(bool $carry, RecordResult $r) => $carry && $r->accepted(),
                true,
            );
        }
        return $this->globalErrorCode === null && $this->httpStatus === 200;
    }

    /**
     * Código Seguro de Verificación devuelto por la AEAT.
     * Solo disponible si la AEAT aceptó el registro.
     */
    public function csv(): ?string
    {
        if ($this->globalCsv !== null) {
            return $this->globalCsv;
        }
        if (count($this->results) === 1) {
            return $this->results[0]->csv();
        }
        return null;
    }

    /**
     * Código de error de la AEAT (si el registro fue rechazado).
     */
    public function errorCode(): ?string
    {
        if ($this->globalErrorCode !== null) {
            return $this->globalErrorCode;
        }
        if (count($this->results) === 1) {
            return $this->results[0]->errorCode();
        }
        return null;
    }

    /**
     * Descripción del error (si el registro fue rechazado).
     */
    public function errorMessage(): ?string
    {
        if ($this->globalErrorMessage !== null) {
            return $this->globalErrorMessage;
        }
        if (count($this->results) === 1) {
            return $this->results[0]->errorMessage();
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Batch helpers
    // -------------------------------------------------------------------------

    /**
     * Resultados individuales por registro (uso principal en lotes).
     *
     * @return RecordResult[]
     */
    public function results(): array
    {
        return $this->results;
    }

    // -------------------------------------------------------------------------
    // Raw data
    // -------------------------------------------------------------------------

    public function httpStatus(): int { return $this->httpStatus; }
    public function rawBody(): string { return $this->rawBody; }
    public function tipoRespuesta(): ?string { return $this->tipoRespuesta; }

    /**
     * Serializa la respuesta completa a array para logging / JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'aceptado'        => $this->accepted(),
            'csv'             => $this->csv(),
            'tipo_respuesta'  => $this->tipoRespuesta,
            'codigo_error'    => $this->errorCode(),
            'mensaje_error'   => $this->errorMessage(),
            'http_status'     => $this->httpStatus,
            'resultados'      => !empty($this->results)
                ? array_map(fn(RecordResult $r) => $r->toArray(), $this->results)
                : null,
        ], fn($v) => $v !== null);
    }

    // -------------------------------------------------------------------------
    // Internal factory — used by AeatClient
    // -------------------------------------------------------------------------

    /**
     * Construye una respuesta de error HTTP (sin XML parseable de la AEAT).
     *
     * @internal
     */
    public static function httpError(int $status, string $body): self
    {
        return new self(
            httpStatus: $status,
            rawBody: $body,
            results: [],
            globalErrorCode: "HTTP_{$status}",
            globalErrorMessage: "HTTP error {$status}: " . substr($body, 0, 200),
        );
    }

    /**
     * Construye una respuesta parseando el XML de la AEAT.
     *
     * @internal
     */
    public static function fromXml(int $httpStatus, string $xmlBody): self
    {
        $dom = new \DOMDocument();

        if (!@$dom->loadXML($xmlBody)) {
            return new self(
                httpStatus: $httpStatus,
                rawBody: $xmlBody,
                results: [],
                globalErrorCode: 'PARSE_ERROR',
                globalErrorMessage: 'No se pudo parsear la respuesta XML de la AEAT.',
            );
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('sf',  'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV/cont/ws/RespuestaSuministro.xsd');
        $xpath->registerNamespace('sum', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV/cont/ws/SuministroLR.xsd');

        $tipoRespuesta    = self::nodeVal($xpath, '//sf:TipoRespuesta') ?? self::nodeVal($xpath, '//*[local-name()="TipoRespuesta"]');
        $globalCsv        = self::nodeVal($xpath, '//*[local-name()="CSV"]');
        $globalErrorCode  = self::nodeVal($xpath, '//*[local-name()="CodigoErrorRegistro"]');
        $globalErrorMsg   = self::nodeVal($xpath, '//*[local-name()="DescripcionErrorRegistro"]');

        // Parse per-record results (RespuestaLinea nodes)
        $results = [];
        $lines   = $xpath->query('//*[local-name()="RespuestaLinea"]') ?: new \DOMNodeList();

        foreach ($lines as $line) {
            $lineXpath      = new \DOMXPath($line->ownerDocument);
            $numFactura     = self::childVal($lineXpath, $line, 'NumSerieFactura')
                           ?? self::childVal($lineXpath, $line, 'IDFactura/NumSerieFactura')
                           ?? '';
            $estado         = self::childVal($lineXpath, $line, 'EstadoRegistro');
            $csvLine        = self::childVal($lineXpath, $line, 'CSV');
            $errorCode      = self::childVal($lineXpath, $line, 'CodigoErrorRegistro');
            $errorMsg       = self::childVal($lineXpath, $line, 'DescripcionErrorRegistro');

            $accepted = in_array($estado, ['Correcto', 'AceptadoConErrores', 'Duplicado'], true)
                     || ($estado === null && $errorCode === null);

            $results[] = new RecordResult(
                invoiceNumber: $numFactura,
                accepted: $accepted,
                csv: $csvLine,
                errorCode: $errorCode,
                errorMessage: $errorMsg,
                rawState: $estado,
            );
        }

        return new self(
            httpStatus: $httpStatus,
            rawBody: $xmlBody,
            results: $results,
            globalErrorCode: empty($results) ? $globalErrorCode : null,
            globalErrorMessage: empty($results) ? $globalErrorMsg : null,
            globalCsv: empty($results) ? $globalCsv : null,
            tipoRespuesta: $tipoRespuesta,
        );
    }

    private static function nodeVal(\DOMXPath $xpath, string $query): ?string
    {
        $nodes = $xpath->query($query);
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }
        $val = trim($nodes->item(0)->nodeValue ?? '');
        return $val !== '' ? $val : null;
    }

    private static function childVal(\DOMXPath $xpath, \DOMNode $ctx, string $localName): ?string
    {
        $nodes = $xpath->query(".//*[local-name()='{$localName}']", $ctx);
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }
        $val = trim($nodes->item(0)->nodeValue ?? '');
        return $val !== '' ? $val : null;
    }
}
