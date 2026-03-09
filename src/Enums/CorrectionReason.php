<?php

declare(strict_types=1);

namespace PhpFacturae\Enums;

/**
 * Motivo de correccion — valores cerrados del XSD FacturaE 3.2.2.
 *
 * Cada case lleva el codigo y la descripcion oficial.
 */
enum CorrectionReason: string
{
    case InvoiceNumber          = '01';
    case InvoiceSeries          = '02';
    case IssueDate              = '03';
    case IssuerName             = '04';
    case RecipientName          = '05';
    case IssuerTaxId            = '06';
    case RecipientTaxId         = '07';
    case IssuerAddress          = '08';
    case RecipientAddress       = '09';
    case TransactionDetail      = '10';
    case TaxRate                = '11';
    case TaxAmount              = '12';
    case TaxPeriod              = '13';
    case InvoiceClass           = '14';
    case LegalLiterals          = '15';
    case TaxableBase            = '16';
    case OutputTaxCalculation   = '80';
    case WithheldTaxCalculation = '81';
    case BaseModifiedReturns    = '82';
    case BaseModifiedDiscounts  = '83';
    case BaseModifiedCourtOrder = '84';
    case BaseModifiedInsolvency = '85';

    public function description(): string
    {
        return match ($this) {
            self::InvoiceNumber          => 'Número de la factura',
            self::InvoiceSeries          => 'Serie de la factura',
            self::IssueDate              => 'Fecha expedición',
            self::IssuerName             => 'Nombre y apellidos/Razón Social-Emisor',
            self::RecipientName          => 'Nombre y apellidos/Razón Social-Receptor',
            self::IssuerTaxId            => 'Identificación fiscal Emisor/obligado',
            self::RecipientTaxId         => 'Identificación fiscal Receptor',
            self::IssuerAddress          => 'Domicilio Emisor/Obligado',
            self::RecipientAddress       => 'Domicilio Receptor',
            self::TransactionDetail      => 'Detalle Operación',
            self::TaxRate                => 'Porcentaje impositivo a aplicar',
            self::TaxAmount              => 'Cuota tributaria a aplicar',
            self::TaxPeriod              => 'Fecha/Periodo a aplicar',
            self::InvoiceClass           => 'Clase de factura',
            self::LegalLiterals          => 'Literales legales',
            self::TaxableBase            => 'Base imponible',
            self::OutputTaxCalculation   => 'Cálculo de cuotas repercutidas',
            self::WithheldTaxCalculation => 'Cálculo de cuotas retenidas',
            self::BaseModifiedReturns    => 'Base imponible modificada por devolución de envases / embalajes',
            self::BaseModifiedDiscounts  => 'Base imponible modificada por descuentos y bonificaciones',
            self::BaseModifiedCourtOrder => 'Base imponible modificada por resolución firme, judicial o administrativa',
            self::BaseModifiedInsolvency => 'Base imponible modificada cuotas repercutidas no satisfechas. Auto de declaración de concurso',
        };
    }
}
