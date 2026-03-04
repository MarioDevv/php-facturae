<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Tests\Verifactu;

use DateTimeImmutable;
use MarioDevv\Rex\Tests\Verifactu\Mother\RecordMother;
use MarioDevv\Rex\Verifactu\Enums\CorrectionType;
use MarioDevv\Rex\Verifactu\Enums\InvoiceType;
use MarioDevv\Rex\Verifactu\Enums\RecordType;
use MarioDevv\Rex\Verifactu\Enums\TaxType;
use MarioDevv\Rex\Verifactu\Exceptions\InvalidRecordException;
use MarioDevv\Rex\Verifactu\RegistrationRecord;
use PHPUnit\Framework\TestCase;

final class RegistrationRecordTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Named constructors
    // -------------------------------------------------------------------------

    public function test_alta_creates_alta_record(): void
    {
        $record = RecordMother::simpleAlta();

        self::assertTrue($record->isAlta());
        self::assertFalse($record->isAnulacion());
        self::assertSame(RecordType::Alta, $record->getRecordType());
    }

    public function test_anulacion_creates_anulacion_record(): void
    {
        $record = RecordMother::anulacion();

        self::assertTrue($record->isAnulacion());
        self::assertFalse($record->isAlta());
        self::assertSame(RecordType::Anulacion, $record->getRecordType());
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function test_simple_alta_has_correct_identity(): void
    {
        $record = RecordMother::simpleAlta();

        self::assertSame(RecordMother::ISSUER_NIF, $record->getIssuerNif());
        self::assertSame('FAC-001', $record->getInvoiceNumber());
        self::assertSame('A', $record->getSeries());
        self::assertSame('AFAC-001', $record->getFullInvoiceNumber());
        self::assertSame(InvoiceType::FullInvoice, $record->getInvoiceType());
        self::assertSame(3025.00, $record->getTotalAmount());
        self::assertSame(525.00, $record->getTotalTax());
    }

    public function test_anulacion_has_correct_identity(): void
    {
        $record = RecordMother::anulacion();

        self::assertSame(RecordMother::ISSUER_NIF, $record->getIssuerNif());
        self::assertSame('FAC-001', $record->getInvoiceNumber());
        self::assertSame('A', $record->getSeries());
    }

    // -------------------------------------------------------------------------
    // Hash calculation
    // -------------------------------------------------------------------------

    public function test_hash_is_64_char_hex_string(): void
    {
        $hash = RecordMother::simpleAlta()->hash();

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
    }

    public function test_hash_is_deterministic(): void
    {
        $record = RecordMother::simpleAlta();

        self::assertSame($record->hash(), $record->hash());
    }

    public function test_different_invoices_produce_different_hashes(): void
    {
        $first  = RecordMother::simpleAlta();
        $second = RecordMother::altaWithIGIC();

        self::assertNotSame($first->hash(), $second->hash());
    }

    public function test_hash_changes_when_invoice_number_changes(): void
    {
        $base    = RecordMother::simpleAlta();
        $altered = RegistrationRecord::alta(
            issuerNif: RecordMother::ISSUER_NIF,
            invoiceNumber: 'FAC-999',
            issueDate: new DateTimeImmutable(RecordMother::INVOICE_DATE),
        )
        ->issuerName(RecordMother::ISSUER_NAME)
        ->series('A')
        ->invoiceType(InvoiceType::FullInvoice)
        ->description('Servicios')
        ->breakdown(taxRate: 21.00, baseAmount: 2500.00, taxAmount: 525.00)
        ->total(3025.00)
        ->generatedAt(new DateTimeImmutable('2025-06-15T10:00:00+02:00'));

        self::assertNotSame($base->hash(), $altered->hash());
    }

    public function test_anulacion_hash_differs_from_alta_hash(): void
    {
        $alta     = RecordMother::simpleAlta();
        $anulacion = RecordMother::anulacion()
            ->generatedAt(new DateTimeImmutable('2025-06-15T10:00:00+02:00'));

        self::assertNotSame($alta->hash(), $anulacion->hash());
    }

    // -------------------------------------------------------------------------
    // Hash chaining
    // -------------------------------------------------------------------------

    public function test_chained_record_includes_previous_hash(): void
    {
        [$first, $second] = RecordMother::batchOfThree();

        self::assertNotNull($second->getPreviousHash());
        self::assertSame($first->hash(), $second->getPreviousHash()->getHash());
    }

    public function test_first_record_has_no_previous_hash(): void
    {
        $record = RecordMother::simpleAlta();

        self::assertNull($record->getPreviousHash());
    }

    public function test_chaining_changes_hash(): void
    {
        $base    = RecordMother::simpleAlta();
        $chained = RecordMother::simpleAlta()->previousHash(
            hash: 'abc123def456' . str_repeat('0', 52),
            issuerNif: RecordMother::ISSUER_NIF,
            invoiceNumber: 'FAC-000',
            issueDate: new DateTimeImmutable('2025-06-01'),
        );

        self::assertNotSame($base->hash(), $chained->hash());
    }

    // -------------------------------------------------------------------------
    // Breakdowns
    // -------------------------------------------------------------------------

    public function test_simple_alta_has_one_breakdown(): void
    {
        $record = RecordMother::simpleAlta();

        self::assertCount(1, $record->getBreakdowns());
    }

    public function test_multiple_rates_alta_has_two_breakdowns(): void
    {
        $record = RecordMother::altaMultipleRates();

        self::assertCount(2, $record->getBreakdowns());
    }

    public function test_igic_breakdown_has_correct_tax_type(): void
    {
        $record     = RecordMother::altaWithIGIC();
        $breakdowns = $record->getBreakdowns();

        self::assertCount(1, $breakdowns);
        self::assertSame(TaxType::IGIC, $breakdowns[0]->getTaxType());
    }

    public function test_exempt_breakdown_has_no_tax_amount(): void
    {
        $record     = RecordMother::altaWithExemption();
        $breakdowns = $record->getBreakdowns();

        self::assertCount(1, $breakdowns);
        self::assertTrue($breakdowns[0]->isExempt());
        self::assertSame(0.0, $breakdowns[0]->getTaxAmount());
        self::assertSame(500.00, $breakdowns[0]->getExemptBaseAmount());
    }

    // -------------------------------------------------------------------------
    // Counterparties
    // -------------------------------------------------------------------------

    public function test_simple_alta_has_one_counterparty(): void
    {
        $record = RecordMother::simpleAlta();

        self::assertCount(1, $record->getCounterparties());
        self::assertSame(RecordMother::RECIPIENT_NIF, $record->getCounterparties()[0]->getNif());
    }

    public function test_simplified_invoice_has_no_counterparty(): void
    {
        $record = RecordMother::altaSimplified();

        self::assertCount(0, $record->getCounterparties());
    }

    // -------------------------------------------------------------------------
    // Rectificativas
    // -------------------------------------------------------------------------

    public function test_rectificativa_has_correction_type(): void
    {
        $record = RecordMother::rectificativa();

        // We access via XML or via the invoice type; the key assertion is the invoice type
        self::assertSame(InvoiceType::CorrectionArt80_1_2_6, $record->getInvoiceType());
        self::assertTrue($record->getInvoiceType()->isCorrection());
    }

    public function test_correction_type_required_for_corrective_invoice(): void
    {
        $record = RegistrationRecord::alta(
            issuerNif: RecordMother::ISSUER_NIF,
            invoiceNumber: 'RECT-BAD',
            issueDate: new DateTimeImmutable('2025-06-20'),
        )
        ->issuerName(RecordMother::ISSUER_NAME)
        ->invoiceType(InvoiceType::CorrectionArt80_1_2_6)
        ->description('Rectificación sin tipo')
        ->breakdown(taxRate: 21.00, baseAmount: 100.00, taxAmount: 21.00)
        ->total(121.00)
        ->addRectifiedInvoice(RecordMother::ISSUER_NIF, 'FAC-001', new DateTimeImmutable('2025-06-15'));

        $this->expectException(InvalidRecordException::class);
        $record->toXml(RecordMother::computerSystem());
    }

    public function test_substitution_requires_rectified_invoices(): void
    {
        $record = RegistrationRecord::alta(
            issuerNif: RecordMother::ISSUER_NIF,
            invoiceNumber: 'RECT-BAD2',
            issueDate: new DateTimeImmutable('2025-06-20'),
        )
        ->issuerName(RecordMother::ISSUER_NAME)
        ->invoiceType(InvoiceType::CorrectionArt80_1_2_6)
        ->description('Rectificación sin facturas referenciadas')
        ->correctionType(CorrectionType::Substitution)
        ->breakdown(taxRate: 21.00, baseAmount: 100.00, taxAmount: 21.00)
        ->total(121.00);

        $this->expectException(InvalidRecordException::class);
        $record->toXml(RecordMother::computerSystem());
    }

    // -------------------------------------------------------------------------
    // QR payload
    // -------------------------------------------------------------------------

    public function test_qr_payload_contains_issuer_nif(): void
    {
        $url = RecordMother::simpleAlta()->qrPayload();

        self::assertStringContainsString(RecordMother::ISSUER_NIF, $url);
    }

    public function test_qr_payload_contains_invoice_number(): void
    {
        $url = RecordMother::simpleAlta()->qrPayload();

        self::assertStringContainsString('AFAC-001', $url);
    }

    public function test_qr_payload_contains_total_amount(): void
    {
        $url = RecordMother::simpleAlta()->qrPayload();

        self::assertStringContainsString('3025.00', $url);
    }

    public function test_qr_payload_uses_staging_url_by_default(): void
    {
        $url = RecordMother::simpleAlta()->qrPayload();

        self::assertStringStartsWith('https://prewww1.aeat.es', $url);
    }

    public function test_qr_payload_uses_production_url_when_set(): void
    {
        $url = RecordMother::simpleAlta()->production(true)->qrPayload();

        self::assertStringStartsWith('https://www1.aeat.es', $url);
    }

    public function test_qr_url_is_alias_of_qr_payload(): void
    {
        $record = RecordMother::simpleAlta();

        self::assertSame($record->qrPayload(), $record->qrUrl());
    }

    // -------------------------------------------------------------------------
    // XML generation
    // -------------------------------------------------------------------------

    public function test_alta_xml_is_valid_xml(): void
    {
        $xml = RecordMother::simpleAlta()->toXml(RecordMother::computerSystem());

        $dom = new \DOMDocument();
        self::assertTrue($dom->loadXML($xml), 'El XML generado no es válido.');
    }

    public function test_anulacion_xml_is_valid_xml(): void
    {
        $xml = RecordMother::anulacion()->toXml(RecordMother::computerSystem());

        $dom = new \DOMDocument();
        self::assertTrue($dom->loadXML($xml), 'El XML de anulación no es válido.');
    }

    public function test_alta_xml_contains_issuer_nif(): void
    {
        $xml = RecordMother::simpleAlta()->toXml(RecordMother::computerSystem());

        self::assertStringContainsString(RecordMother::ISSUER_NIF, $xml);
    }

    public function test_alta_xml_contains_invoice_number(): void
    {
        $xml = RecordMother::simpleAlta()->toXml(RecordMother::computerSystem());

        // Series + number combined
        self::assertStringContainsString('AFAC-001', $xml);
    }

    public function test_alta_xml_contains_hash(): void
    {
        $record = RecordMother::simpleAlta();
        $xml    = $record->toXml(RecordMother::computerSystem());

        self::assertStringContainsString($record->hash(), $xml);
    }

    public function test_alta_xml_contains_tipo_huella_01(): void
    {
        $xml = RecordMother::simpleAlta()->toXml(RecordMother::computerSystem());

        self::assertStringContainsString('<sf:TipoHuella>01</sf:TipoHuella>', $xml);
    }

    public function test_alta_xml_contains_primer_registro_when_no_previous_hash(): void
    {
        $xml = RecordMother::simpleAlta()->toXml(RecordMother::computerSystem());

        self::assertStringContainsString('<sf:PrimerRegistro>S</sf:PrimerRegistro>', $xml);
    }

    public function test_chained_alta_xml_contains_previous_hash(): void
    {
        [, $second] = RecordMother::batchOfThree();
        $xml = $second->toXml(RecordMother::computerSystem());

        self::assertStringNotContainsString('<sf:PrimerRegistro>', $xml);
        self::assertStringContainsString('<sf:RegistroAnterior>', $xml);
    }

    public function test_xml_fails_without_description_for_alta(): void
    {
        $record = RegistrationRecord::alta(
            issuerNif: RecordMother::ISSUER_NIF,
            invoiceNumber: 'FAC-ND',
            issueDate: new DateTimeImmutable('2025-06-15'),
        )
        ->breakdown(taxRate: 21.00, baseAmount: 100.00, taxAmount: 21.00)
        ->total(121.00);

        $this->expectException(InvalidRecordException::class);
        $record->toXml(RecordMother::computerSystem());
    }

    public function test_exempt_breakdown_xml_contains_causa_exencion(): void
    {
        $xml = RecordMother::altaWithExemption()->toXml(RecordMother::computerSystem());

        self::assertStringContainsString('<sf:CausaExencion>E1</sf:CausaExencion>', $xml);
    }

    public function test_rectificativa_xml_contains_tipo_rectificativa(): void
    {
        $xml = RecordMother::rectificativa()->toXml(RecordMother::computerSystem());

        self::assertStringContainsString('<sf:TipoRectificativa>S</sf:TipoRectificativa>', $xml);
    }

    public function test_rectificativa_xml_contains_rectified_invoice_reference(): void
    {
        $xml = RecordMother::rectificativa()->toXml(RecordMother::computerSystem());

        self::assertStringContainsString('<sf:FacturasRectificadas>', $xml);
    }

    public function test_igic_xml_contains_impuesto_02(): void
    {
        $xml = RecordMother::altaWithIGIC()->toXml(RecordMother::computerSystem());

        self::assertStringContainsString('<sf:Impuesto>02</sf:Impuesto>', $xml);
    }

    // -------------------------------------------------------------------------
    // Batch chaining integrity
    // -------------------------------------------------------------------------

    public function test_batch_hash_chain_is_consistent(): void
    {
        [$first, $second, $third] = RecordMother::batchOfThree();

        // Each record's previous hash must equal the prior record's hash
        self::assertNull($first->getPreviousHash());
        self::assertSame($first->hash(), $second->getPreviousHash()->getHash());
        self::assertSame($second->hash(), $third->getPreviousHash()->getHash());
    }

    public function test_batch_all_hashes_are_unique(): void
    {
        $records = RecordMother::batchOfThree();
        $hashes  = array_map(fn($r) => $r->hash(), $records);

        self::assertCount(3, array_unique($hashes));
    }

    // -------------------------------------------------------------------------
    // Immutability
    // -------------------------------------------------------------------------

    public function test_fluent_setters_return_new_instance(): void
    {
        $original = RecordMother::simpleAlta();
        $modified = $original->series('X');

        self::assertNotSame($original, $modified);
        self::assertSame('A', $original->getSeries());
        self::assertSame('X', $modified->getSeries());
    }
}
