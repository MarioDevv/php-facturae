<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Verifactu\Client;

use MarioDevv\Rex\Verifactu\ComputerSystem;
use MarioDevv\Rex\Verifactu\Exceptions\VerifactuException;
use MarioDevv\Rex\Verifactu\RegistrationRecord;

/**
 * Cliente HTTP para el servicio VERI*FACTU de la AEAT.
 *
 * Usa ext-curl para el transporte. El certificado cliente (mutual TLS)
 * se carga desde un fichero PFX/P12 o como par PEM (cert + key).
 *
 * @internal  Usa VerifactuClient como fachada pública.
 */
final class AeatClient
{
    private const TIMEOUT_SECONDS    = 30;
    private const CONTENT_TYPE       = 'application/xml; charset=utf-8';

    /** Ruta al fichero PEM temporal del certificado (si viene de PFX). */
    private ?string $tempCertFile = null;
    private ?string $tempKeyFile  = null;

    private function __construct(
        private readonly AeatEndpoint $endpoint,
        private readonly ?string $certFile,
        private readonly ?string $certPassword,
        private readonly ?string $pemCert,
        private readonly ?string $pemKey,
    ) {}

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    /**
     * Cliente con certificado PFX/P12.
     *
     * @param AeatEndpoint $endpoint   Endpoint de la AEAT (staging o producción)
     * @param string       $pfxPath    Ruta al fichero .pfx / .p12
     * @param string       $password   Contraseña del certificado
     */
    public static function withPfx(AeatEndpoint $endpoint, string $pfxPath, string $password): self
    {
        if (!file_exists($pfxPath)) {
            throw new VerifactuException("Certificado no encontrado: {$pfxPath}");
        }

        return new self(
            endpoint: $endpoint,
            certFile: $pfxPath,
            certPassword: $password,
            pemCert: null,
            pemKey: null,
        );
    }

    /**
     * Cliente con certificado en formato PEM (cert y clave separados).
     *
     * @param AeatEndpoint $endpoint   Endpoint de la AEAT
     * @param string       $pemCert    Ruta al fichero .crt / .pem (certificado)
     * @param string       $pemKey     Ruta al fichero .key (clave privada)
     */
    public static function withPem(AeatEndpoint $endpoint, string $pemCert, string $pemKey): self
    {
        foreach ([$pemCert, $pemKey] as $file) {
            if (!file_exists($file)) {
                throw new VerifactuException("Fichero de certificado no encontrado: {$file}");
            }
        }

        return new self(
            endpoint: $endpoint,
            certFile: null,
            certPassword: null,
            pemCert: $pemCert,
            pemKey: $pemKey,
        );
    }

    /**
     * Cliente sin certificado (solo útil para staging sin mutual TLS).
     */
    public static function withoutCert(AeatEndpoint $endpoint): self
    {
        return new self(
            endpoint: $endpoint,
            certFile: null,
            certPassword: null,
            pemCert: null,
            pemKey: null,
        );
    }

    // -------------------------------------------------------------------------
    // Submit
    // -------------------------------------------------------------------------

    /**
     * Envía un registro a la AEAT.
     */
    public function submit(
        RegistrationRecord $record,
        ComputerSystem $system,
        string $obligadoNif,
        string $obligadoNombre,
    ): SubmitResponse {
        return $this->submitBatch([$record], $system, $obligadoNif, $obligadoNombre);
    }

    /**
     * Envía un lote de registros a la AEAT (máx. 1000 por envío según spec AEAT).
     *
     * @param RegistrationRecord[] $records
     */
    public function submitBatch(
        array $records,
        ComputerSystem $system,
        string $obligadoNif,
        string $obligadoNombre,
    ): SubmitResponse {
        if (empty($records)) {
            throw new VerifactuException('El lote de registros no puede estar vacío.');
        }
        if (count($records) > 1000) {
            throw new VerifactuException('El lote no puede superar los 1000 registros por envío.');
        }

        $issueDate = $records[0]->getIssueDate();
        $ejercicio = $issueDate->format('Y');
        $periodo   = $issueDate->format('m');

        $xml = SuministroXmlBuilder::build(
            records: $records,
            system: $system,
            obligadoNif: $obligadoNif,
            obligadoNombre: $obligadoNombre,
            ejercicio: $ejercicio,
            periodo: $periodo,
        );

        return $this->post($this->endpoint->value, $xml);
    }

    // -------------------------------------------------------------------------
    // Query
    // -------------------------------------------------------------------------

    /**
     * Consulta el estado de un registro en la AEAT.
     */
    public function query(
        string $issuerNif,
        string $invoiceNumber,
        \DateTimeImmutable $issueDate,
        string $obligadoNif,
    ): SubmitResponse {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS(
            'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV/cont/ws/SuministroLR.xsd',
            'sum:ConsultaLR'
        );
        $dom->appendChild($root);

        $cabecera  = $dom->createElement('sum:Cabecera');
        $obligado  = $dom->createElement('sum:ObligadoTributario');
        $obligado->appendChild(self::domText($dom, 'sum:NIF', $obligadoNif));
        $cabecera->appendChild($obligado);
        $root->appendChild($cabecera);

        $filtro = $dom->createElement('sum:FiltroConsulta');
        $filtro->appendChild(self::domText($dom, 'sum:IDEmisorFactura', $issuerNif));
        $filtro->appendChild(self::domText($dom, 'sum:NumSerieFactura', $invoiceNumber));
        $filtro->appendChild(self::domText($dom, 'sum:FechaExpedicionFactura', $issueDate->format('d-m-Y')));
        $root->appendChild($filtro);

        $xml = $dom->saveXML() ?: '';

        return $this->post($this->endpoint->queryUrl(), $xml);
    }

    // -------------------------------------------------------------------------
    // HTTP transport
    // -------------------------------------------------------------------------

    private function post(string $url, string $xmlBody): SubmitResponse
    {
        if (!extension_loaded('curl')) {
            throw new VerifactuException('ext-curl es necesaria para comunicarse con la AEAT.');
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $xmlBody,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: ' . self::CONTENT_TYPE,
                'Content-Length: ' . strlen($xmlBody),
                'Accept: application/xml',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        // Mutual TLS
        if ($this->certFile !== null) {
            // PFX — convertir a PEM en fichero temporal
            [$certPemPath, $keyPemPath] = $this->pfxToPem($this->certFile, $this->certPassword ?? '');
            curl_setopt($ch, CURLOPT_SSLCERT, $certPemPath);
            curl_setopt($ch, CURLOPT_SSLKEY, $keyPemPath);
        } elseif ($this->pemCert !== null) {
            curl_setopt($ch, CURLOPT_SSLCERT, $this->pemCert);
            curl_setopt($ch, CURLOPT_SSLKEY, $this->pemKey);
        }

        $body     = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        $this->cleanupTempFiles();

        if ($body === false) {
            throw new VerifactuException("Error de red comunicando con la AEAT: {$curlErr}");
        }

        if ($status >= 500) {
            return SubmitResponse::httpError($status, (string) $body);
        }

        return SubmitResponse::fromXml($status, (string) $body);
    }

    /**
     * Extrae cert + key PEM desde un PFX y los escribe en ficheros temporales.
     *
     * @return array{0: string, 1: string}  [certPath, keyPath]
     */
    private function pfxToPem(string $pfxPath, string $password): array
    {
        $pfxContent = file_get_contents($pfxPath);
        if ($pfxContent === false) {
            throw new VerifactuException("No se pudo leer el certificado PFX: {$pfxPath}");
        }

        $certs = [];
        if (!openssl_pkcs12_read($pfxContent, $certs, $password)) {
            throw new VerifactuException('No se pudo abrir el certificado PFX. Comprueba la contraseña.');
        }

        $this->tempCertFile = tempnam(sys_get_temp_dir(), 'rex_cert_');
        $this->tempKeyFile  = tempnam(sys_get_temp_dir(), 'rex_key_');

        file_put_contents($this->tempCertFile, $certs['cert']);
        file_put_contents($this->tempKeyFile,  $certs['pkey']);

        return [$this->tempCertFile, $this->tempKeyFile];
    }

    private function cleanupTempFiles(): void
    {
        foreach ([$this->tempCertFile, $this->tempKeyFile] as $file) {
            if ($file !== null && file_exists($file)) {
                unlink($file);
            }
        }
        $this->tempCertFile = null;
        $this->tempKeyFile  = null;
    }

    private static function domText(\DOMDocument $dom, string $tag, string $value): \DOMElement
    {
        $el = $dom->createElement($tag);
        $el->appendChild($dom->createTextNode($value));
        return $el;
    }

    public function __destruct()
    {
        $this->cleanupTempFiles();
    }
}
