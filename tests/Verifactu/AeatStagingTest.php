<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Tests\Verifactu;

use DateTimeImmutable;
use MarioDevv\Rex\Verifactu\ComputerSystem;
use MarioDevv\Rex\Verifactu\Enums\CorrectionType;
use MarioDevv\Rex\Verifactu\Enums\ExemptionCause;
use MarioDevv\Rex\Verifactu\Enums\InvoiceType;
use MarioDevv\Rex\Verifactu\Enums\RegimeType;
use MarioDevv\Rex\Verifactu\Enums\TaxType;
use MarioDevv\Rex\Verifactu\RegistrationRecord;
use MarioDevv\Rex\Verifactu\VerifactuClient;
use PHPUnit\Framework\TestCase;


final class AeatStagingTest extends TestCase
{
    private VerifactuClient $client;
    private ComputerSystem  $system;
    private string          $nif;
    private string          $nombre;

    // ─── Setup ───────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        $certPath     = getenv('VERIFACTU_CERT_PATH') ?: null;
        $certPass     = getenv('VERIFACTU_CERT_PASS') ?: '';
        $this->nif    = getenv('VERIFACTU_NIF') ?: '';
        $this->nombre = getenv('VERIFACTU_NOMBRE') ?: '';

        if (!$certPath || !file_exists($certPath) || !$this->nif) {
            $this->markTestSkipped(
                "Tests de staging omitidos.\n" .
                "Configura: VERIFACTU_CERT_PATH, VERIFACTU_CERT_PASS, VERIFACTU_NIF, VERIFACTU_NOMBRE"
            );
        }

        $this->system = ComputerSystem::create('IpsoFactum', 'v2.0.0')
            ->producer($this->nif, $this->nombre)
            ->installationId('01');

        $this->client = VerifactuClient::staging($this->system, $certPath, $certPass)
            ->obligado($this->nif, $this->nombre);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Número de factura único para evitar duplicados entre ejecuciones. */
    private function num(string $prefix): string
    {
        return $prefix . '-' . date('YmdHis') . '-' . random_int(100, 999);
    }

    /** Imprime la respuesta completa de la AEAT en consola. */
    private function dump(string $label, $response): void
    {
        $data = is_array($response) ? $response : $response->toArray();

        fwrite(STDOUT, "\n\n" . str_repeat('─', 60) . "\n");
        fwrite(STDOUT, "  {$label}\n");
        fwrite(STDOUT, str_repeat('─', 60) . "\n");
        fwrite(STDOUT, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    }

    /** Assertions comunes a toda respuesta de la AEAT. */
    private function assertValidAeatResponse($response, string $context): void
    {
        self::assertSame(
            200,
            $response->httpStatus(),
            "[{$context}] La AEAT debe responder HTTP 200. Body: " . substr($response->rawBody(), 0, 500),
        );

        self::assertNotEmpty(
            $response->rawBody(),
            "[{$context}] La AEAT debe devolver cuerpo XML",
        );

        // El XML de respuesta debe ser parseable
        $dom = new \DOMDocument();
        self::assertTrue(
            @$dom->loadXML($response->rawBody()),
            "[{$context}] La respuesta de la AEAT debe ser XML válido.\nBody: " . $response->rawBody(),
        );
    }

    /** Assertions cuando la AEAT acepta el registro. */
    private function assertAccepted($response, string $context): void
    {
        self::assertTrue(
            $response->accepted(),
            "[{$context}] AEAT rechazó el registro.\n" .
            "  Código:  " . ($response->errorCode() ?? 'n/a') . "\n" .
            "  Mensaje: " . ($response->errorMessage() ?? 'n/a') . "\n" .
            "  XML:     " . substr($response->rawBody(), 0, 800),
        );

        self::assertNotEmpty(
            $response->csv(),
            "[{$context}] La AEAT debe devolver un CSV cuando acepta el registro",
        );

        // El CSV tiene formato alfanumérico estándar de la AEAT
        self::assertMatchesRegularExpression(
            '/^[A-Z0-9]{16,32}$/',
            $response->csv(),
            "[{$context}] El CSV debe ser alfanumérico de 16-32 caracteres",
        );
    }

    // =========================================================================
    // 1. ALTA SIMPLE — IVA 21%
    // =========================================================================

    public function test_staging_alta_simple_iva_21(): void
    {
        $num = $this->num('T-SIMPLE');

        $record = RegistrationRecord::alta(
            issuerNif    : $this->nif,
            invoiceNumber: $num,
            issueDate    : new DateTimeImmutable('today'),
        )
            ->issuerName($this->nombre)
            ->invoiceType(InvoiceType::FullInvoice)
            ->description('Test staging — alta simple IVA 21%')
            ->regime(RegimeType::General)
            ->counterparty('51234567B', 'Cliente Test')
            ->breakdown(taxRate: 21.0, baseAmount: 100.0, taxAmount: 21.0)
            ->total(121.0);

        $response = $this->client->submit($record);

        $this->dump('ALTA SIMPLE IVA 21% → AEAT', $response);
        $this->assertValidAeatResponse($response, 'alta simple');
        $this->assertAccepted($response, 'alta simple');

        fwrite(STDOUT, "\n  ✅ CSV: " . $response->csv() . "\n");
    }

    // =========================================================================
    // 2. ALTA IGIC — Canarias
    // =========================================================================

    public function test_staging_alta_igic_canarias(): void
    {
        $num = $this->num('T-IGIC');

        $record = RegistrationRecord::alta(
            issuerNif    : $this->nif,
            invoiceNumber: $num,
            issueDate    : new DateTimeImmutable('today'),
        )
            ->issuerName($this->nombre)
            ->invoiceType(InvoiceType::FullInvoice)
            ->description('Test staging — alta IGIC Canarias 7%')
            ->regime(RegimeType::IPSI_IGIC)
            ->taxType(TaxType::IGIC)
            ->counterparty('51234567B', 'Cliente Canarias')
            ->breakdown(taxRate: 7.0, baseAmount: 500.0, taxAmount: 35.0)
            ->total(535.0);

        $response = $this->client->submit($record);

        $this->dump('ALTA IGIC 7% → AEAT', $response);
        $this->assertValidAeatResponse($response, 'alta IGIC');
        $this->assertAccepted($response, 'alta IGIC');
    }

    // =========================================================================
    // 3. ALTA EXENTA — Art. 20 LIVA
    // =========================================================================

    public function test_staging_alta_exenta_art20(): void
    {
        $num = $this->num('T-EXENTA');

        $record = RegistrationRecord::alta(
            issuerNif    : $this->nif,
            invoiceNumber: $num,
            issueDate    : new DateTimeImmutable('today'),
        )
            ->issuerName($this->nombre)
            ->invoiceType(InvoiceType::FullInvoice)
            ->description('Test staging — operación exenta art. 20 LIVA')
            ->regime(RegimeType::General)
            ->counterparty('51234567B', 'Cliente Exento')
            ->exemptBreakdown(cause: ExemptionCause::Art20, baseAmount: 300.0)
            ->total(300.0, 0.0);

        $response = $this->client->submit($record);

        $this->dump('ALTA EXENTA Art.20 → AEAT', $response);
        $this->assertValidAeatResponse($response, 'alta exenta');
        $this->assertAccepted($response, 'alta exenta');
    }

    // =========================================================================
    // 4. FACTURA SIMPLIFICADA — sin destinatario identificado
    // =========================================================================

    public function test_staging_factura_simplificada_sin_destinatario(): void
    {
        $num = $this->num('T-TICKET');

        $record = RegistrationRecord::alta(
            issuerNif    : $this->nif,
            invoiceNumber: $num,
            issueDate    : new DateTimeImmutable('today'),
        )
            ->issuerName($this->nombre)
            ->invoiceType(InvoiceType::SimplifiedInvoice)
            ->description('Test staging — ticket simplificado')
            ->regime(RegimeType::General)
            ->noRecipientId()
            ->simplifiedArt7273()
            ->breakdown(taxRate: 21.0, baseAmount: 50.0, taxAmount: 10.50)
            ->total(60.50);

        $response = $this->client->submit($record);

        $this->dump('FACTURA SIMPLIFICADA (ticket) → AEAT', $response);
        $this->assertValidAeatResponse($response, 'factura simplificada');
        $this->assertAccepted($response, 'factura simplificada');
    }

    // =========================================================================
    // 5. RECTIFICATIVA R1 por sustitución
    // =========================================================================

    public function test_staging_rectificativa_sustitucion(): void
    {
        // Primero enviamos la original
        $numOriginal    = $this->num('T-ORIG');
        $numRectificada = $this->num('T-RECT');
        $fechaHoy       = new DateTimeImmutable('today');

        $original = RegistrationRecord::alta(
            issuerNif    : $this->nif,
            invoiceNumber: $numOriginal,
            issueDate    : $fechaHoy,
        )
            ->issuerName($this->nombre)
            ->invoiceType(InvoiceType::FullInvoice)
            ->description('Test staging — factura original a rectificar')
            ->regime(RegimeType::General)
            ->counterparty('51234567B', 'Cliente Test')
            ->breakdown(taxRate: 21.0, baseAmount: 100.0, taxAmount: 21.0)
            ->total(121.0);

        $rOrig = $this->client->submit($original);
        $this->assertValidAeatResponse($rOrig, 'original (previa a rectificativa)');
        $this->assertAccepted($rOrig, 'original (previa a rectificativa)');

        // Ahora la rectificativa
        $rectificativa = RegistrationRecord::alta(
            issuerNif    : $this->nif,
            invoiceNumber: $numRectificada,
            issueDate    : $fechaHoy,
        )
            ->issuerName($this->nombre)
            ->invoiceType(InvoiceType::CorrectionArt80_1_2_6)
            ->description('Test staging — rectificativa por sustitución')
            ->regime(RegimeType::General)
            ->counterparty('51234567B', 'Cliente Test')
            ->correctionType(CorrectionType::Substitution)
            ->addRectifiedInvoice(
                issuerNif    : $this->nif,
                invoiceNumber: $numOriginal,
                issueDate    : $fechaHoy,
            )
            ->breakdown(taxRate: 21.0, baseAmount: 120.0, taxAmount: 25.20)
            ->correctionAmount(correctedBase: 120.0, correctedTax: 25.20)
            ->total(145.20);

        $rRect = $this->client->submit($rectificativa);

        $this->dump('RECTIFICATIVA R1 SUSTITUCIÓN → AEAT', $rRect);
        $this->assertValidAeatResponse($rRect, 'rectificativa sustitución');
        $this->assertAccepted($rRect, 'rectificativa sustitución');

        fwrite(STDOUT, "\n  Original  CSV: " . $rOrig->csv() . "\n");
        fwrite(STDOUT, "  Rectif.  CSV: " . $rRect->csv() . "\n");
    }

    // =========================================================================
    // 6. ANULACIÓN
    // =========================================================================

    public function test_staging_anulacion(): void
    {
        // Primero enviamos la factura a anular
        $num      = $this->num('T-ANUL');
        $fechaHoy = new DateTimeImmutable('today');

        $factura = RegistrationRecord::alta(
            issuerNif    : $this->nif,
            invoiceNumber: $num,
            issueDate    : $fechaHoy,
        )
            ->issuerName($this->nombre)
            ->invoiceType(InvoiceType::FullInvoice)
            ->description('Test staging — factura a anular')
            ->counterparty('51234567B', 'Cliente Test')
            ->breakdown(taxRate: 21.0, baseAmount: 100.0, taxAmount: 21.0)
            ->total(121.0);

        $rAlta = $this->client->submit($factura);
        $this->assertAccepted($rAlta, 'alta (previa a anulación)');

        // Anulación encadenada con la huella del alta
        $anulacion = RegistrationRecord::anulacion(
            issuerNif    : $this->nif,
            invoiceNumber: $num,
            issueDate    : $fechaHoy,
        )
            ->issuerName($this->nombre)
            ->previousHash(
                hash         : $factura->hash(),
                issuerNif    : $this->nif,
                invoiceNumber: $factura->getFullInvoiceNumber(),
                issueDate    : $fechaHoy,
            );

        $rAnul = $this->client->submit($anulacion);

        $this->dump('ANULACIÓN → AEAT', $rAnul);
        $this->assertValidAeatResponse($rAnul, 'anulación');
        $this->assertAccepted($rAnul, 'anulación');

        fwrite(STDOUT, "\n  Alta CSV:  " . $rAlta->csv() . "\n");
        fwrite(STDOUT, "  Anul CSV:  " . $rAnul->csv() . "\n");
    }

    // =========================================================================
    // 7. LOTE ENCADENADO — 3 registros en un solo POST
    // =========================================================================

    public function test_staging_lote_encadenado_tres_registros(): void
    {
        $fechaHoy = new DateTimeImmutable('today');

        $r1 = RegistrationRecord::alta(
            issuerNif    : $this->nif,
            invoiceNumber: $this->num('T-L1'),
            issueDate    : $fechaHoy,
        )
            ->issuerName($this->nombre)
            ->description('Lote staging — registro 1')
            ->counterparty('51234567B', 'Cliente Test')
            ->breakdown(21.0, 100.0, 21.0)
            ->total(121.0);

        $r2 = RegistrationRecord::alta(
            issuerNif    : $this->nif,
            invoiceNumber: $this->num('T-L2'),
            issueDate    : $fechaHoy,
        )
            ->issuerName($this->nombre)
            ->description('Lote staging — registro 2')
            ->counterparty('51234567B', 'Cliente Test')
            ->breakdown(10.0, 200.0, 20.0)
            ->total(220.0)
            ->previousHash(
                hash         : $r1->hash(),
                issuerNif    : $this->nif,
                invoiceNumber: $r1->getFullInvoiceNumber(),
                issueDate    : $fechaHoy,
            );

        $r3 = RegistrationRecord::alta(
            issuerNif    : $this->nif,
            invoiceNumber: $this->num('T-L3'),
            issueDate    : $fechaHoy,
        )
            ->issuerName($this->nombre)
            ->description('Lote staging — registro 3')
            ->counterparty('51234567B', 'Cliente Test')
            ->breakdown(21.0, 50.0, 10.50)
            ->total(60.50)
            ->previousHash(
                hash         : $r2->hash(),
                issuerNif    : $this->nif,
                invoiceNumber: $r2->getFullInvoiceNumber(),
                issueDate    : $fechaHoy,
            );

        $response = $this->client->submitBatch([$r1, $r2, $r3]);

        $this->dump('LOTE 3 REGISTROS ENCADENADOS → AEAT', $response);
        $this->assertValidAeatResponse($response, 'lote 3 registros');

        // Verificar resultados individuales
        $results = $response->results();
        self::assertCount(3, $results, 'La AEAT debe devolver un resultado por cada registro del lote');

        foreach ($results as $i => $result) {
            $n = $i + 1;
            self::assertTrue(
                $result->accepted(),
                "Registro {$n} del lote rechazado.\n" .
                "  Código:  " . ($result->errorCode() ?? 'n/a') . "\n" .
                "  Mensaje: " . ($result->errorMessage() ?? 'n/a'),
            );
            self::assertNotEmpty($result->csv(), "Registro {$n}: debe tener CSV");
            fwrite(STDOUT, "\n  Reg {$n} CSV: " . $result->csv());
        }

        fwrite(STDOUT, "\n");
    }

    // =========================================================================
    // 8. CONSULTA de un registro ya enviado
    // =========================================================================

    public function test_staging_consulta_registro_enviado(): void
    {
        // Enviamos una factura y luego la consultamos
        $num      = $this->num('T-QUERY');
        $fechaHoy = new DateTimeImmutable('today');

        $factura = RegistrationRecord::alta(
            issuerNif    : $this->nif,
            invoiceNumber: $num,
            issueDate    : $fechaHoy,
        )
            ->issuerName($this->nombre)
            ->description('Test staging — consulta posterior')
            ->counterparty('51234567B', 'Cliente Test')
            ->breakdown(21.0, 100.0, 21.0)
            ->total(121.0);

        $rAlta = $this->client->submit($factura);
        $this->assertAccepted($rAlta, 'alta (previa a consulta)');

        // Consulta por NIF + número + fecha
        $rQuery = $this->client->query(
            issuerNif    : $this->nif,
            invoiceNumber: $factura->getFullInvoiceNumber(),
            issueDate    : $fechaHoy,
        );

        $this->dump('CONSULTA REGISTRO → AEAT', $rQuery);

        self::assertSame(200, $rQuery->httpStatus(), 'La consulta debe responder HTTP 200');
        self::assertNotEmpty($rQuery->rawBody(), 'La consulta debe devolver XML');

        fwrite(STDOUT, "\n  Alta CSV:    " . $rAlta->csv() . "\n");
        fwrite(STDOUT, "  Query HTTP: " . $rQuery->httpStatus() . "\n");
    }

    // =========================================================================
    // 9. RECHAZO ESPERADO — XML inválido (NIF ficticio)
    // =========================================================================

    public function test_staging_respuesta_de_error_es_parseable(): void
    {
        // Enviamos con un NIF de destinatario deliberadamente inválido para la AEAT.
        // El objetivo no es que sea aceptado, sino que la respuesta de error
        // sea parseable y contenga código + mensaje descriptivo.
        $record = RegistrationRecord::alta(
            issuerNif    : $this->nif,
            invoiceNumber: $this->num('T-ERR'),
            issueDate    : new DateTimeImmutable('today'),
        )
            ->issuerName($this->nombre)
            ->description('Test staging — forzar error de validación')
            ->counterparty('00000000T', 'NIF Inválido Hacienda')  // NIF que la AEAT rechazará
            ->breakdown(21.0, 100.0, 21.0)
            ->total(121.0);

        $response = $this->client->submit($record);

        $this->dump('RESPUESTA DE ERROR → AEAT', $response);

        // Independientemente de si acepta o rechaza, la respuesta debe ser XML válido
        self::assertSame(200, $response->httpStatus());
        self::assertNotEmpty($response->rawBody());

        $dom = new \DOMDocument();
        self::assertTrue(
            @$dom->loadXML($response->rawBody()),
            'La respuesta de error también debe ser XML válido',
        );

        if (!$response->accepted()) {
            // Si rechazó, debe haber código y mensaje
            self::assertNotEmpty(
                $response->errorCode() ?? $response->results()[0]?->errorCode() ?? '',
                'Un rechazo debe incluir código de error',
            );

            fwrite(STDOUT, "\n  ⚠️  Rechazado (esperado). Código: " . $response->errorCode() . "\n");
            fwrite(STDOUT, "  Mensaje: " . $response->errorMessage() . "\n");
        } else {
            fwrite(STDOUT, "\n  ℹ️  La AEAT aceptó el NIF de prueba (staging permisivo)\n");
        }
    }
}
