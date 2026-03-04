<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Tests\Verifactu\Mother;

use DateTimeImmutable;
use MarioDevv\Rex\Verifactu\ComputerSystem;
use MarioDevv\Rex\Verifactu\Enums\CorrectionType;
use MarioDevv\Rex\Verifactu\Enums\ExemptionCause;
use MarioDevv\Rex\Verifactu\Enums\InvoiceType;
use MarioDevv\Rex\Verifactu\Enums\RegimeType;
use MarioDevv\Rex\Verifactu\Enums\TaxType;
use MarioDevv\Rex\Verifactu\RegistrationRecord;

/**
 * Object Mother para pruebas del paquete Verifactu.
 *
 * Proporciona instancias de RegistrationRecord preconfiguradas
 * para los escenarios de test más habituales.
 */
final class RecordMother
{
    public const ISSUER_NIF     = 'B76123456';
    public const ISSUER_NAME    = 'Atlantic Systems S.L.';
    public const INVOICE_DATE   = '2025-06-15';
    public const RECIPIENT_NIF  = '51234567B';
    public const RECIPIENT_NAME = 'Carlos Méndez Torres';

    // -------------------------------------------------------------------------
    // Alta records
    // -------------------------------------------------------------------------

    /**
     * Alta simple: una línea de IVA al 21%, cliente nacional.
     */
    public static function simpleAlta(): RegistrationRecord
    {
        return RegistrationRecord::alta(
            issuerNif: self::ISSUER_NIF,
            invoiceNumber: 'FAC-001',
            issueDate: new DateTimeImmutable(self::INVOICE_DATE),
        )
        ->issuerName(self::ISSUER_NAME)
        ->series('A')
        ->invoiceType(InvoiceType::FullInvoice)
        ->description('Servicios de consultoría')
        ->regime(RegimeType::General)
        ->counterparty(self::RECIPIENT_NIF, self::RECIPIENT_NAME)
        ->breakdown(taxRate: 21.00, baseAmount: 2500.00, taxAmount: 525.00)
        ->total(3025.00)
        ->generatedAt(new DateTimeImmutable('2025-06-15T10:00:00+02:00'));
    }

    /**
     * Alta con IGIC (Canarias) al 7%.
     */
    public static function altaWithIGIC(): RegistrationRecord
    {
        return RegistrationRecord::alta(
            issuerNif: self::ISSUER_NIF,
            invoiceNumber: 'FAC-002',
            issueDate: new DateTimeImmutable(self::INVOICE_DATE),
        )
        ->issuerName(self::ISSUER_NAME)
        ->invoiceType(InvoiceType::FullInvoice)
        ->description('Servicios de desarrollo web')
        ->regime(RegimeType::IPSI_IGIC)
        ->taxType(TaxType::IGIC)
        ->counterparty(self::RECIPIENT_NIF, self::RECIPIENT_NAME)
        ->breakdown(taxRate: 7.00, baseAmount: 1000.00, taxAmount: 70.00)
        ->total(1070.00)
        ->generatedAt(new DateTimeImmutable('2025-06-15T11:00:00+01:00'));
    }

    /**
     * Alta con múltiples tipos impositivos (21% y 10%).
     */
    public static function altaMultipleRates(): RegistrationRecord
    {
        return RegistrationRecord::alta(
            issuerNif: self::ISSUER_NIF,
            invoiceNumber: 'FAC-003',
            issueDate: new DateTimeImmutable(self::INVOICE_DATE),
        )
        ->issuerName(self::ISSUER_NAME)
        ->invoiceType(InvoiceType::FullInvoice)
        ->description('Equipamiento y servicios')
        ->regime(RegimeType::General)
        ->counterparty(self::RECIPIENT_NIF, self::RECIPIENT_NAME)
        ->breakdown(taxRate: 21.00, baseAmount: 800.00, taxAmount: 168.00)
        ->breakdown(taxRate: 10.00, baseAmount: 200.00, taxAmount: 20.00)
        ->total(1188.00, 188.00)
        ->generatedAt(new DateTimeImmutable('2025-06-15T12:00:00+02:00'));
    }

    /**
     * Alta con operación exenta (art. 20 LIVA).
     */
    public static function altaWithExemption(): RegistrationRecord
    {
        return RegistrationRecord::alta(
            issuerNif: self::ISSUER_NIF,
            invoiceNumber: 'FAC-004',
            issueDate: new DateTimeImmutable(self::INVOICE_DATE),
        )
        ->issuerName(self::ISSUER_NAME)
        ->invoiceType(InvoiceType::FullInvoice)
        ->description('Servicios médicos')
        ->regime(RegimeType::General)
        ->counterparty(self::RECIPIENT_NIF, self::RECIPIENT_NAME)
        ->exemptBreakdown(cause: ExemptionCause::Art20, baseAmount: 500.00)
        ->total(500.00, 0.00)
        ->generatedAt(new DateTimeImmutable('2025-06-15T12:00:00+02:00'));
    }

    /**
     * Factura simplificada sin identificación del destinatario.
     */
    public static function altaSimplified(): RegistrationRecord
    {
        return RegistrationRecord::alta(
            issuerNif: self::ISSUER_NIF,
            invoiceNumber: 'TICK-001',
            issueDate: new DateTimeImmutable(self::INVOICE_DATE),
        )
        ->issuerName(self::ISSUER_NAME)
        ->invoiceType(InvoiceType::SimplifiedInvoice)
        ->description('Venta al público')
        ->regime(RegimeType::General)
        ->noRecipientId(true)
        ->simplifiedArt7273(true)
        ->breakdown(taxRate: 21.00, baseAmount: 100.00, taxAmount: 21.00)
        ->total(121.00)
        ->generatedAt(new DateTimeImmutable('2025-06-15T14:00:00+02:00'));
    }

    /**
     * Alta con recargo de equivalencia.
     */
    public static function altaWithSurcharge(): RegistrationRecord
    {
        return RegistrationRecord::alta(
            issuerNif: self::ISSUER_NIF,
            invoiceNumber: 'FAC-005',
            issueDate: new DateTimeImmutable(self::INVOICE_DATE),
        )
        ->issuerName(self::ISSUER_NAME)
        ->invoiceType(InvoiceType::FullInvoice)
        ->description('Venta bienes con recargo de equivalencia')
        ->regime(RegimeType::General)
        ->counterparty(self::RECIPIENT_NIF, self::RECIPIENT_NAME)
        ->breakdownWithSurcharge(
            taxRate: 21.00,
            baseAmount: 1000.00,
            taxAmount: 210.00,
            surchargeRate: 5.20,
            surchargeAmount: 52.00,
        )
        ->total(1262.00, 262.00)
        ->generatedAt(new DateTimeImmutable('2025-06-15T15:00:00+02:00'));
    }

    // -------------------------------------------------------------------------
    // Corrective records
    // -------------------------------------------------------------------------

    /**
     * Factura rectificativa por sustitución (R1 + tipo S).
     */
    public static function rectificativa(): RegistrationRecord
    {
        return RegistrationRecord::alta(
            issuerNif: self::ISSUER_NIF,
            invoiceNumber: 'RECT-001',
            issueDate: new DateTimeImmutable('2025-06-20'),
        )
        ->issuerName(self::ISSUER_NAME)
        ->series('R')
        ->invoiceType(InvoiceType::CorrectionArt80_1_2_6)
        ->description('Rectificación FAC-001')
        ->regime(RegimeType::General)
        ->counterparty(self::RECIPIENT_NIF, self::RECIPIENT_NAME)
        ->correctionType(CorrectionType::Substitution)
        ->addRectifiedInvoice(
            issuerNif: self::ISSUER_NIF,
            invoiceNumber: 'FAC-001',
            issueDate: new DateTimeImmutable(self::INVOICE_DATE),
            series: 'A',
        )
        ->breakdown(taxRate: 21.00, baseAmount: 2500.00, taxAmount: 525.00)
        ->correctionAmount(correctedBase: 2500.00, correctedTax: 525.00)
        ->total(3025.00)
        ->generatedAt(new DateTimeImmutable('2025-06-20T09:00:00+02:00'));
    }

    /**
     * Factura rectificativa por diferencias.
     */
    public static function rectificativaDiferencias(): RegistrationRecord
    {
        return RegistrationRecord::alta(
            issuerNif: self::ISSUER_NIF,
            invoiceNumber: 'RECT-002',
            issueDate: new DateTimeImmutable('2025-06-20'),
        )
        ->issuerName(self::ISSUER_NAME)
        ->series('R')
        ->invoiceType(InvoiceType::CorrectionArt80_1_2_6)
        ->description('Rectificación por diferencias FAC-001')
        ->regime(RegimeType::General)
        ->counterparty(self::RECIPIENT_NIF, self::RECIPIENT_NAME)
        ->correctionType(CorrectionType::Differences)
        ->addRectifiedInvoice(
            issuerNif: self::ISSUER_NIF,
            invoiceNumber: 'FAC-001',
            issueDate: new DateTimeImmutable(self::INVOICE_DATE),
            series: 'A',
        )
        ->breakdown(taxRate: 21.00, baseAmount: -200.00, taxAmount: -42.00)
        ->total(-242.00, -42.00)
        ->generatedAt(new DateTimeImmutable('2025-06-20T09:30:00+02:00'));
    }

    // -------------------------------------------------------------------------
    // Cancellation records
    // -------------------------------------------------------------------------

    /**
     * Registro de anulación de una factura emitida.
     */
    public static function anulacion(): RegistrationRecord
    {
        return RegistrationRecord::anulacion(
            issuerNif: self::ISSUER_NIF,
            invoiceNumber: 'FAC-001',
            issueDate: new DateTimeImmutable(self::INVOICE_DATE),
        )
        ->issuerName(self::ISSUER_NAME)
        ->series('A')
        ->generatedAt(new DateTimeImmutable('2025-06-16T08:00:00+02:00'));
    }

    // -------------------------------------------------------------------------
    // Batch
    // -------------------------------------------------------------------------

    /**
     * Lote de tres registros encadenados (alta → alta → anulación).
     *
     * @return RegistrationRecord[]
     */
    public static function batchOfThree(): array
    {
        $first = self::simpleAlta();

        $second = RegistrationRecord::alta(
            issuerNif: self::ISSUER_NIF,
            invoiceNumber: 'FAC-002',
            issueDate: new DateTimeImmutable('2025-06-16'),
        )
        ->issuerName(self::ISSUER_NAME)
        ->series('A')
        ->invoiceType(InvoiceType::FullInvoice)
        ->description('Segunda factura')
        ->regime(RegimeType::General)
        ->counterparty(self::RECIPIENT_NIF, self::RECIPIENT_NAME)
        ->breakdown(taxRate: 21.00, baseAmount: 1000.00, taxAmount: 210.00)
        ->total(1210.00)
        ->generatedAt(new DateTimeImmutable('2025-06-16T10:00:00+02:00'))
        ->previousHash(
            hash: $first->hash(),
            issuerNif: $first->getIssuerNif(),
            invoiceNumber: $first->getInvoiceNumber(),
            issueDate: $first->getIssueDate(),
        );

        $third = RegistrationRecord::anulacion(
            issuerNif: self::ISSUER_NIF,
            invoiceNumber: 'FAC-002',
            issueDate: new DateTimeImmutable('2025-06-16'),
        )
        ->issuerName(self::ISSUER_NAME)
        ->series('A')
        ->generatedAt(new DateTimeImmutable('2025-06-17T08:00:00+02:00'))
        ->previousHash(
            hash: $second->hash(),
            issuerNif: $second->getIssuerNif(),
            invoiceNumber: $second->getInvoiceNumber(),
            issueDate: $second->getIssueDate(),
        );

        return [$first, $second, $third];
    }

    // -------------------------------------------------------------------------
    // Computer System
    // -------------------------------------------------------------------------

    public static function computerSystem(): ComputerSystem
    {
        return ComputerSystem::create('TestERP', 'v1.0.0')
            ->producer(self::ISSUER_NIF, self::ISSUER_NAME)
            ->installationId('01');
    }
}
