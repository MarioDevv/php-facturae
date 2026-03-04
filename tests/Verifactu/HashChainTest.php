<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Tests\Verifactu;

use DateTimeImmutable;
use MarioDevv\Rex\Tests\Verifactu\Mother\RecordMother;
use MarioDevv\Rex\Verifactu\Enums\InvoiceType;
use MarioDevv\Rex\Verifactu\RegistrationRecord;
use PHPUnit\Framework\TestCase;

/**
 * Tests specifically focused on the hash chaining mechanism (Encadenamiento).
 *
 * @covers \MarioDevv\Rex\Verifactu\RegistrationRecord
 */
final class HashChainTest extends TestCase
{
    public function test_hash_format_is_sha256_hex(): void
    {
        $hash = RecordMother::simpleAlta()->hash();

        self::assertSame(64, strlen($hash));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
    }

    public function test_hash_is_cached_after_first_calculation(): void
    {
        $record = RecordMother::simpleAlta();

        $hash1 = $record->hash();
        $hash2 = $record->hash();

        self::assertSame($hash1, $hash2);
    }

    public function test_adding_previous_hash_changes_current_hash(): void
    {
        $base = RecordMother::simpleAlta();

        $withPrev = $base->previousHash(
            hash: str_repeat('a', 64),
            issuerNif: RecordMother::ISSUER_NIF,
            invoiceNumber: 'FAC-000',
            issueDate: new DateTimeImmutable('2025-01-01'),
        );

        self::assertNotSame($base->hash(), $withPrev->hash());
    }

    public function test_chain_of_five_records_maintains_integrity(): void
    {
        $records = [];
        $prev    = null;

        for ($i = 1; $i <= 5; $i++) {
            $day = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $record = RegistrationRecord::alta(
                issuerNif: RecordMother::ISSUER_NIF,
                invoiceNumber: "FAC-00{$i}",
                issueDate: new DateTimeImmutable("2025-06-{$day}"),
            )
            ->issuerName(RecordMother::ISSUER_NAME)
            ->invoiceType(InvoiceType::FullInvoice)
            ->description("Factura {$i}")
            ->breakdown(taxRate: 21.00, baseAmount: 100.00, taxAmount: 21.00)
            ->total(121.00)
            ->generatedAt(new DateTimeImmutable("2025-06-{$day}T10:00:00+02:00"));

            if ($prev !== null) {
                $record = $record->previousHash(
                    hash: $prev->hash(),
                    issuerNif: $prev->getIssuerNif(),
                    invoiceNumber: $prev->getInvoiceNumber(),
                    issueDate: $prev->getIssueDate(),
                );
            }

            $records[] = $record;
            $prev      = $record;
        }

        // Verify chain integrity
        self::assertNull($records[0]->getPreviousHash());

        for ($i = 1; $i < 5; $i++) {
            self::assertSame(
                $records[$i - 1]->hash(),
                $records[$i]->getPreviousHash()->getHash(),
                "Chain broken at record {$i}"
            );
        }

        // All hashes are unique
        $hashes = array_map(fn($r) => $r->hash(), $records);
        self::assertCount(5, array_unique($hashes));
    }

    public function test_anulacion_is_chainable(): void
    {
        $alta = RecordMother::simpleAlta();
        $anulacion = RecordMother::anulacion()->previousHash(
            hash: $alta->hash(),
            issuerNif: $alta->getIssuerNif(),
            invoiceNumber: $alta->getInvoiceNumber(),
            issueDate: $alta->getIssueDate(),
        );

        self::assertSame($alta->hash(), $anulacion->getPreviousHash()->getHash());
        self::assertNotSame($alta->hash(), $anulacion->hash());
    }

    public function test_hash_input_for_alta_includes_invoice_type(): void
    {
        // Two records identical except invoice type → different hashes
        $f1 = RegistrationRecord::alta(
            issuerNif: RecordMother::ISSUER_NIF,
            invoiceNumber: 'FAC-X',
            issueDate: new DateTimeImmutable('2025-06-15'),
        )
        ->issuerName(RecordMother::ISSUER_NAME)
        ->invoiceType(InvoiceType::FullInvoice)
        ->description('Test')
        ->breakdown(taxRate: 21.00, baseAmount: 100.00, taxAmount: 21.00)
        ->total(121.00)
        ->generatedAt(new DateTimeImmutable('2025-06-15T10:00:00+02:00'));

        $f2 = RegistrationRecord::alta(
            issuerNif: RecordMother::ISSUER_NIF,
            invoiceNumber: 'FAC-X',
            issueDate: new DateTimeImmutable('2025-06-15'),
        )
        ->issuerName(RecordMother::ISSUER_NAME)
        ->invoiceType(InvoiceType::SimplifiedInvoice)
        ->description('Test')
        ->breakdown(taxRate: 21.00, baseAmount: 100.00, taxAmount: 21.00)
        ->total(121.00)
        ->generatedAt(new DateTimeImmutable('2025-06-15T10:00:00+02:00'));

        self::assertNotSame($f1->hash(), $f2->hash());
    }
}
