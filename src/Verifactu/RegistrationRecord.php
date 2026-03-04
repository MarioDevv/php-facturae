<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Verifactu;

use DateTimeImmutable;
use MarioDevv\Rex\Verifactu\Entities\BreakdownItem;
use MarioDevv\Rex\Verifactu\Entities\CorrectionAmount;
use MarioDevv\Rex\Verifactu\Entities\Counterparty;
use MarioDevv\Rex\Verifactu\Entities\InvoiceReference;
use MarioDevv\Rex\Verifactu\Entities\PreviousHash;
use MarioDevv\Rex\Verifactu\Enums\CorrectionType;
use MarioDevv\Rex\Verifactu\Enums\ExemptionCause;
use MarioDevv\Rex\Verifactu\Enums\InvoiceType;
use MarioDevv\Rex\Verifactu\Enums\OperationType;
use MarioDevv\Rex\Verifactu\Enums\RecordType;
use MarioDevv\Rex\Verifactu\Enums\RegimeType;
use MarioDevv\Rex\Verifactu\Enums\TaxType;
use MarioDevv\Rex\Verifactu\Exceptions\InvalidRecordException;
use MarioDevv\Rex\Verifactu\Xml\XmlBuilder;

/**
 * Registro de facturación Verifactu.
 *
 * Punto de entrada principal para crear registros de alta y anulación
 * conformes al sistema VERI*FACTU de la AEAT (Orden HAC/1177/2024).
 *
 * @example
 * $record = RegistrationRecord::alta(
 *     issuerNif: 'B76123456',
 *     invoiceNumber: 'FAC-001',
 *     issueDate: new DateTimeImmutable('2025-06-15'),
 * )
 * ->series('A')
 * ->invoiceType(InvoiceType::FullInvoice)
 * ->description('Servicios de consultoría')
 * ->regime(RegimeType::General)
 * ->counterparty('51234567B', 'Carlos Méndez Torres')
 * ->breakdown(taxRate: 21.00, baseAmount: 2500.00, taxAmount: 525.00)
 * ->total(3025.00);
 */
final class RegistrationRecord
{
    // Core identity
    private string $series = '';
    private string $issuerName = '';

    // Alta-specific
    private InvoiceType $invoiceType = InvoiceType::FullInvoice;
    private string $description = '';
    private float $totalTax = 0.0;
    private float $totalAmount = 0.0;
    private RegimeType $defaultRegime = RegimeType::General;
    private TaxType $defaultTaxType = TaxType::IVA;

    /** @var BreakdownItem[] */
    private array $breakdowns = [];

    /** @var Counterparty[] */
    private array $counterparties = [];

    // Rectificativas
    private ?CorrectionType $correctionType = null;

    /** @var InvoiceReference[] */
    private array $rectifiedInvoices = [];

    /** @var InvoiceReference[] */
    private array $substitutedInvoices = [];
    private ?CorrectionAmount $correctionAmount = null;
    private ?DateTimeImmutable $operationDate = null;

    // Flags
    private bool $simplifiedArt7273 = false;
    private bool $noRecipientId = false;
    private bool $macrodato = false;

    // Chaining
    private ?PreviousHash $previousHash = null;

    // Computed lazily
    private ?string $computedHash = null;
    private ?DateTimeImmutable $generatedAt = null;

    // QR environment
    private bool $production = false;

    private function __construct(
        private readonly RecordType $recordType,
        private readonly string $issuerNif,
        private readonly string $invoiceNumber,
        private readonly DateTimeImmutable $issueDate,
    ) {}

    // -------------------------------------------------------------------------
    // Named constructors
    // -------------------------------------------------------------------------

    /**
     * Crea un registro de alta.
     */
    public static function alta(
        string $issuerNif,
        string $invoiceNumber,
        DateTimeImmutable $issueDate,
    ): self {
        return new self(
            recordType: RecordType::Alta,
            issuerNif: $issuerNif,
            invoiceNumber: $invoiceNumber,
            issueDate: $issueDate,
        );
    }

    /**
     * Crea un registro de anulación.
     */
    public static function anulacion(
        string $issuerNif,
        string $invoiceNumber,
        DateTimeImmutable $issueDate,
    ): self {
        return new self(
            recordType: RecordType::Anulacion,
            issuerNif: $issuerNif,
            invoiceNumber: $invoiceNumber,
            issueDate: $issueDate,
        );
    }

    // -------------------------------------------------------------------------
    // Fluent setters
    // -------------------------------------------------------------------------

    /**
     * Serie de la factura (prefijo antes del número).
     */
    public function series(string $series): self
    {
        $clone = clone $this;
        $clone->series = $series;
        $clone->computedHash = null;
        return $clone;
    }

    /**
     * Nombre o razón social del emisor (campo NombreRazonEmisor en XML).
     */
    public function issuerName(string $name): self
    {
        $clone = clone $this;
        $clone->issuerName = $name;
        $clone->computedHash = null;
        return $clone;
    }

    /**
     * Tipo de factura (InvoiceType enum).
     */
    public function invoiceType(InvoiceType $type): self
    {
        $clone = clone $this;
        $clone->invoiceType = $type;
        $clone->computedHash = null;
        return $clone;
    }

    /**
     * Descripción de la operación (DescripcionOperacion).
     */
    public function description(string $description): self
    {
        $clone = clone $this;
        $clone->description = $description;
        return $clone;
    }

    /**
     * Régimen de IVA por defecto que se aplicará a los desgloses sin régimen explícito.
     */
    public function regime(RegimeType $regime): self
    {
        $clone = clone $this;
        $clone->defaultRegime = $regime;
        return $clone;
    }

    /**
     * Tipo de impuesto por defecto (IVA, IGIC, IPSI).
     */
    public function taxType(TaxType $type): self
    {
        $clone = clone $this;
        $clone->defaultTaxType = $type;
        return $clone;
    }

    /**
     * Añade un destinatario con NIF nacional.
     * Puede llamarse varias veces para operaciones con múltiples destinatarios.
     */
    public function counterparty(string $nif, string $name): self
    {
        $clone = clone $this;
        $clone->counterparties[] = Counterparty::withNif($nif, $name);
        return $clone;
    }

    /**
     * Añade un destinatario extranjero.
     */
    public function foreignCounterparty(string $name, string $id, string $countryCode, string $idType = '04'): self
    {
        $clone = clone $this;
        $clone->counterparties[] = Counterparty::foreign($name, $id, $countryCode, $idType);
        return $clone;
    }

    /**
     * Añade un desglose de impuesto (sujeto y no exento).
     * Puede llamarse varias veces para múltiples tipos impositivos.
     *
     * @param float         $taxRate    Tipo impositivo (ej. 21.00)
     * @param float         $baseAmount Base imponible
     * @param float         $taxAmount  Cuota repercutida
     * @param RegimeType|null $regime   Régimen (usa el por defecto si no se especifica)
     * @param TaxType|null  $taxType    Tipo impuesto (usa el por defecto si no se especifica)
     */
    public function breakdown(
        float $taxRate,
        float $baseAmount,
        float $taxAmount,
        ?RegimeType $regime = null,
        ?TaxType $taxType = null,
    ): self {
        $clone = clone $this;
        $clone->computedHash = null;

        $item = BreakdownItem::create($regime ?? $clone->defaultRegime)
            ->taxType($taxType ?? $clone->defaultTaxType)
            ->operationType(OperationType::SubjectNotExempt)
            ->rates($taxRate, $baseAmount, $taxAmount);

        $clone->breakdowns[] = $item;
        return $clone;
    }

    /**
     * Añade un desglose exento con causa de exención.
     */
    public function exemptBreakdown(
        ExemptionCause $cause,
        float $baseAmount,
        ?RegimeType $regime = null,
        ?TaxType $taxType = null,
    ): self {
        $clone = clone $this;
        $clone->computedHash = null;

        $item = BreakdownItem::create($regime ?? $clone->defaultRegime)
            ->taxType($taxType ?? $clone->defaultTaxType)
            ->exemptionCause($cause, $baseAmount);

        $clone->breakdowns[] = $item;
        return $clone;
    }

    /**
     * Añade un desglose con recargo de equivalencia.
     */
    public function breakdownWithSurcharge(
        float $taxRate,
        float $baseAmount,
        float $taxAmount,
        float $surchargeRate,
        float $surchargeAmount,
        ?RegimeType $regime = null,
        ?TaxType $taxType = null,
    ): self {
        $clone = clone $this;
        $clone->computedHash = null;

        $item = BreakdownItem::create($regime ?? $clone->defaultRegime)
            ->taxType($taxType ?? $clone->defaultTaxType)
            ->operationType(OperationType::SubjectNotExempt)
            ->rates($taxRate, $baseAmount, $taxAmount)
            ->surcharge($surchargeRate, $surchargeAmount);

        $clone->breakdowns[] = $item;
        return $clone;
    }

    /**
     * Importe total de la factura (ImporteTotal).
     * La cuota total se calcula de los desgloses, o se puede pasar explícitamente.
     */
    public function total(float $totalAmount, ?float $totalTax = null): self
    {
        $clone = clone $this;
        $clone->computedHash = null;
        $clone->totalAmount = $totalAmount;
        $clone->totalTax = $totalTax ?? $clone->calculateTotalTax();
        return $clone;
    }

    /**
     * Tipo de rectificativa (sustitución o diferencias).
     * Solo aplica a facturas rectificativas (R1-R5).
     */
    public function correctionType(CorrectionType $type): self
    {
        $clone = clone $this;
        $clone->computedHash = null;
        $clone->correctionType = $type;
        return $clone;
    }

    /**
     * Añade una referencia a factura rectificada.
     */
    public function addRectifiedInvoice(
        string $issuerNif,
        string $invoiceNumber,
        DateTimeImmutable $issueDate,
        ?string $series = null,
    ): self {
        $clone = clone $this;
        $clone->rectifiedInvoices[] = new InvoiceReference($issuerNif, $invoiceNumber, $issueDate, $series);
        return $clone;
    }

    /**
     * Añade una referencia a factura sustituida.
     */
    public function addSubstitutedInvoice(
        string $issuerNif,
        string $invoiceNumber,
        DateTimeImmutable $issueDate,
        ?string $series = null,
    ): self {
        $clone = clone $this;
        $clone->substitutedInvoices[] = new InvoiceReference($issuerNif, $invoiceNumber, $issueDate, $series);
        return $clone;
    }

    /**
     * Importes de rectificación (solo para rectificativas por sustitución).
     */
    public function correctionAmount(float $correctedBase, float $correctedTax, float $correctedSurcharge = 0.0): self
    {
        $clone = clone $this;
        $clone->computedHash = null;
        $clone->correctionAmount = new CorrectionAmount($correctedBase, $correctedTax, $correctedSurcharge);
        return $clone;
    }

    /**
     * Fecha de la operación si difiere de la fecha de expedición.
     */
    public function operationDate(DateTimeImmutable $date): self
    {
        $clone = clone $this;
        $clone->operationDate = $date;
        return $clone;
    }

    /**
     * Indica que es una factura simplificada art. 7-14 del Reglamento o art. 72-73 LIVA.
     */
    public function simplifiedArt7273(bool $value = true): self
    {
        $clone = clone $this;
        $clone->simplifiedArt7273 = $value;
        return $clone;
    }

    /**
     * Indica operación sin identificación del destinatario (art. 6.1.d RD 1619/2012).
     */
    public function noRecipientId(bool $value = true): self
    {
        $clone = clone $this;
        $clone->noRecipientId = $value;
        return $clone;
    }

    /**
     * Marca la factura como macrodato (importe total > 100M €).
     */
    public function macrodato(bool $value = true): self
    {
        $clone = clone $this;
        $clone->macrodato = $value;
        return $clone;
    }

    /**
     * Establece el hash del registro anterior para el encadenamiento.
     *
     * @param string               $hash          Huella del registro anterior
     * @param string               $issuerNif     NIF del emisor del registro anterior
     * @param string               $invoiceNumber Número de factura del registro anterior
     * @param DateTimeImmutable    $issueDate     Fecha del registro anterior
     */
    public function previousHash(
        string $hash,
        string $issuerNif,
        string $invoiceNumber,
        DateTimeImmutable $issueDate,
    ): self {
        $clone = clone $this;
        $clone->computedHash = null;
        $clone->previousHash = new PreviousHash($issuerNif, $invoiceNumber, $issueDate, $hash);
        return $clone;
    }

    /**
     * Fija el momento de generación del registro (por defecto = ahora al calcular el hash).
     */
    public function generatedAt(DateTimeImmutable $at): self
    {
        $clone = clone $this;
        $clone->computedHash = null;
        $clone->generatedAt = $at;
        return $clone;
    }

    /**
     * Marca el entorno de producción para las URLs de QR.
     */
    public function production(bool $value = true): self
    {
        $clone = clone $this;
        $clone->production = $value;
        return $clone;
    }

    // -------------------------------------------------------------------------
    // Computed outputs
    // -------------------------------------------------------------------------

    /**
     * Calcula y devuelve la huella SHA-256 del registro (Huella Verifactu).
     *
     * La huella se calcula sobre la concatenación de campos clave separados por '&'
     * tal como exige la Orden HAC/1177/2024.
     */
    public function hash(): string
    {
        if ($this->computedHash === null) {
            $this->computedHash = $this->calculateHash();
        }
        return $this->computedHash;
    }

    /**
     * Genera el payload de URL para el código QR de la factura.
     */
    public function qrPayload(): string
    {
        $baseUrl = $this->production
            ? 'https://www1.aeat.es/wlpl/TIKE-CONT/ValidarQR'
            : 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ValidarQR';

        $numSerie = $this->series !== '' ? $this->series . $this->invoiceNumber : $this->invoiceNumber;

        $params = http_build_query([
            'nif'      => $this->issuerNif,
            'numserie' => $numSerie,
            'fecha'    => $this->issueDate->format('d-m-Y'),
            'importe'  => number_format($this->totalAmount, 2, '.', ''),
        ]);

        return $baseUrl . '?' . $params;
    }

    /**
     * Alias de qrPayload().
     */
    public function qrUrl(): string
    {
        return $this->qrPayload();
    }

    /**
     * Genera el XML del registro conforme al esquema AEAT.
     *
     * @throws InvalidRecordException Si faltan datos obligatorios
     */
    public function toXml(ComputerSystem $system): string
    {
        $this->validate();
        $system->validate();

        $generatedAt = $this->generatedAt ?? new DateTimeImmutable();

        if ($this->recordType === RecordType::Alta) {
            return XmlBuilder::forAlta(
                issuerNif: $this->issuerNif,
                issuerName: $this->issuerName,
                invoiceNumber: $this->invoiceNumber,
                series: $this->series,
                issueDate: $this->issueDate,
                generatedAt: $generatedAt,
                hash: $this->hash(),
                invoiceType: $this->invoiceType,
                description: $this->description,
                totalTax: $this->totalTax,
                totalAmount: $this->totalAmount,
                system: $system,
                breakdowns: $this->breakdowns,
                previousHash: $this->previousHash,
                counterparties: $this->counterparties ?: null,
                correctionType: $this->correctionType,
                rectifiedInvoices: $this->rectifiedInvoices ?: null,
                substitutedInvoices: $this->substitutedInvoices ?: null,
                correctionAmount: $this->correctionAmount,
                operationDate: $this->operationDate,
                simplifiedArt7273: $this->simplifiedArt7273,
                noRecipientId: $this->noRecipientId,
                macrodato: $this->macrodato,
            );
        }

        // Anulación
        return XmlBuilder::forAnulacion(
            issuerNif: $this->issuerNif,
            issuerName: $this->issuerName,
            invoiceNumber: $this->invoiceNumber,
            series: $this->series,
            issueDate: $this->issueDate,
            generatedAt: $generatedAt,
            hash: $this->hash(),
            system: $system,
            previousHash: $this->previousHash,
        );
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getRecordType(): RecordType { return $this->recordType; }
    public function getIssuerNif(): string { return $this->issuerNif; }
    public function getInvoiceNumber(): string { return $this->invoiceNumber; }
    public function getIssueDate(): DateTimeImmutable { return $this->issueDate; }
    public function getSeries(): string { return $this->series; }
    public function getTotalAmount(): float { return $this->totalAmount; }
    public function getTotalTax(): float { return $this->totalTax; }
    public function getInvoiceType(): InvoiceType { return $this->invoiceType; }
    public function isAlta(): bool { return $this->recordType === RecordType::Alta; }
    public function isAnulacion(): bool { return $this->recordType === RecordType::Anulacion; }

    /** @return BreakdownItem[] */
    public function getBreakdowns(): array { return $this->breakdowns; }

    /** @return Counterparty[] */
    public function getCounterparties(): array { return $this->counterparties; }

    public function getPreviousHash(): ?PreviousHash { return $this->previousHash; }

    public function getFullInvoiceNumber(): string
    {
        return $this->series !== '' ? $this->series . $this->invoiceNumber : $this->invoiceNumber;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Calcula la huella SHA-256 según la especificación de la Orden HAC/1177/2024.
     *
     * Campos para alta:
     *   IDEmisorFactura & NumSerieFactura & FechaExpedicionFactura & TipoFactura &
     *   CuotaTotal & ImporteTotal & Huella (anterior) & FechaHoraHusoGenRegistro
     *
     * Campos para anulación:
     *   IDEmisorFactura & NumSerieFactura & FechaExpedicionFactura &
     *   Huella (anterior) & FechaHoraHusoGenRegistro
     */
    private function calculateHash(): string
    {
        $generatedAt = $this->generatedAt ?? new DateTimeImmutable();
        $numSerie = $this->series !== '' ? $this->series . $this->invoiceNumber : $this->invoiceNumber;
        $prevHuella = $this->previousHash?->getHash() ?? '';

        if ($this->recordType === RecordType::Alta) {
            $input = implode('&', [
                'IDEmisorFactura='         . $this->issuerNif,
                'NumSerieFactura='         . $numSerie,
                'FechaExpedicionFactura='  . $this->issueDate->format('d-m-Y'),
                'TipoFactura='             . $this->invoiceType->value,
                'CuotaTotal='              . number_format($this->totalTax, 2, '.', ''),
                'ImporteTotal='            . number_format($this->totalAmount, 2, '.', ''),
                'Huella='                  . $prevHuella,
                'FechaHoraHusoGenRegistro=' . $generatedAt->format('Y-m-d\TH:i:sP'),
            ]);
        } else {
            $input = implode('&', [
                'IDEmisorFactura='         . $this->issuerNif,
                'NumSerieFactura='         . $numSerie,
                'FechaExpedicionFactura='  . $this->issueDate->format('d-m-Y'),
                'Huella='                  . $prevHuella,
                'FechaHoraHusoGenRegistro=' . $generatedAt->format('Y-m-d\TH:i:sP'),
            ]);
        }

        return hash('sha256', $input);
    }

    private function calculateTotalTax(): float
    {
        return array_reduce(
            $this->breakdowns,
            fn(float $carry, BreakdownItem $item) => $carry + $item->getTaxAmount(),
            0.0,
        );
    }

    private function validate(): void
    {
        if ($this->recordType === RecordType::Alta) {
            if ($this->description === '') {
                throw InvalidRecordException::missingField('description');
            }

            if ($this->invoiceType->isCorrection() && $this->correctionType === null) {
                throw InvalidRecordException::correctionTypeRequiresInvoiceType($this->invoiceType->value);
            }

            if (
                $this->correctionType === CorrectionType::Substitution
                && empty($this->rectifiedInvoices)
                && empty($this->substitutedInvoices)
            ) {
                throw InvalidRecordException::rectifiedInvoicesRequiredForSubstitution();
            }
        }
    }
}
