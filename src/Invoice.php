<?php

declare(strict_types=1);

namespace PhpFacturae;

use DateTimeImmutable;
use PhpFacturae\Entities\Line;
use PhpFacturae\Entities\Payment;
use PhpFacturae\Entities\TaxBreakdown;
use PhpFacturae\Entities\Attachment;
use PhpFacturae\Enums\CorrectionMethod;
use PhpFacturae\Enums\CorrectionReason;
use PhpFacturae\Enums\InvoiceType;
use PhpFacturae\Enums\PaymentMethod;
use PhpFacturae\Enums\Schema;
use PhpFacturae\Enums\SpecialTaxableEvent;
use PhpFacturae\Enums\Tax;
use PhpFacturae\Enums\UnitOfMeasure;
use PhpFacturae\Exceptions\InvoiceValidationException;
use PhpFacturae\Exporter\XmlExporter;
use PhpFacturae\Signer\InvoiceSigner;
use PhpFacturae\Validation\InvoiceValidator;

final class Invoice
{
    private string             $number;
    private string             $series             = '';
    private DateTimeImmutable  $issueDate;
    private ?DateTimeImmutable $operationDate      = null;
    private ?DateTimeImmutable $billingPeriodStart = null;
    private ?DateTimeImmutable $billingPeriodEnd   = null;
    private Schema             $schema             = Schema::V3_2_2;
    private InvoiceType        $type               = InvoiceType::Full;
    private string             $currency           = 'EUR';
    private ?string            $description        = null;
    private ?Party             $seller             = null;
    private ?Party             $buyer              = null;
    /** @var Line[] */
    private array $lines = [];
    /** @var Payment[] */
    private array              $payments              = [];
    private ?string            $correctedNumber       = null;
    private ?string            $correctedSeries       = null;
    private ?CorrectionMethod  $correctionMethod      = null;
    private ?CorrectionReason  $correctionReason      = null;
    private ?DateTimeImmutable $correctionPeriodStart = null;
    private ?DateTimeImmutable $correctionPeriodEnd   = null;
    private bool               $isCorrective          = false;
    private ?string            $legalLiteral          = null;
    private ?InvoiceSigner     $signer                = null;
    /** @var array<int, array{reason: string, rate?: float, amount: float}> */
    private array $generalDiscounts = [];
    /** @var array<int, array{reason: string, rate?: float, amount: float}> */
    private array $generalCharges = [];
    /** @var Attachment[] */
    private array $attachments = [];

    private function __construct(string $number)
    {
        $this->number    = $number;
        $this->issueDate = new DateTimeImmutable();
    }

    public static function create(string $number): self
    {
        return new self($number);
    }

    // ─── Core ────────────────────────────────────────────

    public function series(string $series): self
    {
        $this->series = $series;
        return $this;
    }

    public function date(string|DateTimeImmutable $date): self
    {
        $this->issueDate = is_string($date) ? new DateTimeImmutable($date) : $date;
        return $this;
    }

    public function operationDate(string|DateTimeImmutable $date): self
    {
        $this->operationDate = is_string($date) ? new DateTimeImmutable($date) : $date;
        return $this;
    }

    public function billingPeriod(
        string|DateTimeImmutable $from,
        string|DateTimeImmutable $to,
    ): self
    {
        $this->billingPeriodStart = is_string($from) ? new DateTimeImmutable($from) : $from;
        $this->billingPeriodEnd   = is_string($to) ? new DateTimeImmutable($to) : $to;
        return $this;
    }

    public function schema(Schema $schema): self
    {
        $this->schema = $schema;
        return $this;
    }

    public function type(InvoiceType $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function currency(string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function seller(Party $seller): self
    {
        $this->seller = $seller;
        return $this;
    }

    public function buyer(Party $buyer): self
    {
        $this->buyer = $buyer;
        return $this;
    }

    // ─── Lines ───────────────────────────────────────────

    /**
     * Linea con atajos fiscales espanoles.
     *
     *     ->line('Lampara', quantity: 3, price: 20.14, vat: 21)
     *     ->line('Consultoria', price: 500, vat: 21, irpf: 15)
     *     ->line('Producto Canarias', price: 100, igic: 7)
     *     ->line('Joyeria', price: 200, vat: 21, surcharge: 5.2)
     *     ->line('Electricidad', price: 80, vat: 21, unit: UnitOfMeasure::KWh)
     *     ->line('Impuesto especial retenido', price: 10, vat: 21, ie: 4, ieWithheld: true)
     */
    public function line(
        string         $description,
        float          $price,
        float          $quantity = 1,
        ?float         $vat = null,
        ?float         $irpf = null,
        ?float         $igic = null,
        ?float         $surcharge = null,
        ?float         $ie = null,
        bool           $ieWithheld = false,
        ?float         $discount = null,
        ?string        $articleCode = null,
        ?string        $detailedDescription = null,
        ?UnitOfMeasure $unit = null,
    ): self
    {
        $taxes = [];

        if ($vat !== null) {
            $taxes[] = new TaxBreakdown(Tax::IVA, $vat, surchargeRate: $surcharge);
        }
        if ($irpf !== null) {
            $taxes[] = new TaxBreakdown(Tax::IRPF, $irpf);
        }
        if ($igic !== null) {
            $taxes[] = new TaxBreakdown(Tax::IGIC, $igic);
        }
        if ($ie !== null) {
            $taxes[] = new TaxBreakdown(Tax::IE, $ie, isWithholding: $ieWithheld);
        }

        $this->lines[] = new Line(
            description        : $description,
            quantity           : $quantity,
            unitPrice          : $price,
            taxes              : $taxes,
            articleCode        : $articleCode,
            discount           : $discount,
            detailedDescription: $detailedDescription,
            unitOfMeasure      : $unit,
        );

        return $this;
    }

    /**
     * Linea exenta con motivo fiscal.
     *
     *     ->exemptLine('Formacion FUNDAE', price: 2000, reason: 'Exenta art. 20 LIVA')
     */
    public function exemptLine(
        string         $description,
        float          $price,
        float          $quantity = 1,
        ?string        $reason = null,
        ?float         $discount = null,
        ?string        $articleCode = null,
        ?UnitOfMeasure $unit = null,
    ): self
    {
        $this->lines[] = new Line(
            description              : $description,
            quantity                 : $quantity,
            unitPrice                : $price,
            taxes                    : [],
            articleCode              : $articleCode,
            discount                 : $discount,
            unitOfMeasure            : $unit,
            specialTaxableEvent      : SpecialTaxableEvent::Exempt,
            specialTaxableEventReason: $reason,
        );

        return $this;
    }

    /**
     * Linea con configuracion de impuestos personalizada.
     *
     * @param TaxBreakdown[] $taxes
     */
    public function customLine(
        string               $description,
        float                $price,
        array                $taxes,
        float                $quantity = 1,
        ?float               $discount = null,
        ?string              $articleCode = null,
        ?UnitOfMeasure       $unit = null,
        ?SpecialTaxableEvent $specialTaxableEvent = null,
        ?string              $specialTaxableEventReason = null,
    ): self
    {
        $this->lines[] = new Line(
            description              : $description,
            quantity                 : $quantity,
            unitPrice                : $price,
            taxes                    : $taxes,
            articleCode              : $articleCode,
            discount                 : $discount,
            unitOfMeasure            : $unit,
            specialTaxableEvent      : $specialTaxableEvent,
            specialTaxableEventReason: $specialTaxableEventReason,
        );

        return $this;
    }

    // ─── Payments ────────────────────────────────────────

    public function payment(Payment $payment): self
    {
        $this->payments[] = $payment;
        return $this;
    }

    public function transferPayment(
        string  $iban,
        ?string $dueDate = null,
        ?float  $amount = null,
    ): self
    {
        return $this->addPayment(PaymentMethod::Transfer, $dueDate, $amount, $iban);
    }

    public function cashPayment(?string $dueDate = null, ?float $amount = null): self
    {
        return $this->addPayment(PaymentMethod::Cash, $dueDate, $amount);
    }

    public function cardPayment(?string $dueDate = null, ?float $amount = null): self
    {
        return $this->addPayment(PaymentMethod::Card, $dueDate, $amount);
    }

    public function directDebitPayment(
        string  $iban,
        ?string $dueDate = null,
        ?float  $amount = null,
    ): self
    {
        return $this->addPayment(PaymentMethod::DirectDebit, $dueDate, $amount, $iban);
    }

    public function splitPayments(
        PaymentMethod $method,
        int           $installments,
        string        $firstDueDate,
        int           $intervalDays = 30,
        ?string       $iban = null,
    ): self
    {
        $date = new DateTimeImmutable($firstDueDate);

        for ($i = 0; $i < $installments; $i++) {
            $this->payments[] = new Payment(
                method           : $method,
                dueDate          : $date,
                amount           : null,
                iban             : $iban ? str_replace(' ', '', $iban) : null,
                installmentIndex : $i,
                totalInstallments: $installments,
            );

            $date = $date->modify("+{$intervalDays} days");
        }

        return $this;
    }

    // ─── Corrective ──────────────────────────────────────

    public function corrects(
        string                        $invoiceNumber,
        CorrectionReason              $reason = CorrectionReason::TransactionDetail,
        CorrectionMethod              $method = CorrectionMethod::FullReplacement,
        ?string                       $series = null,
        string|DateTimeImmutable|null $periodStart = null,
        string|DateTimeImmutable|null $periodEnd = null,
    ): self
    {
        $this->isCorrective     = true;
        $this->correctedNumber  = $invoiceNumber;
        $this->correctedSeries  = $series;
        $this->correctionMethod = $method;
        $this->correctionReason = $reason;

        $this->correctionPeriodStart = $periodStart !== null
            ? (is_string($periodStart) ? new DateTimeImmutable($periodStart) : $periodStart)
            : null;
        $this->correctionPeriodEnd   = $periodEnd !== null
            ? (is_string($periodEnd) ? new DateTimeImmutable($periodEnd) : $periodEnd)
            : null;

        return $this;
    }

    public function legalLiteral(string $literal): self
    {
        $this->legalLiteral = $literal;
        return $this;
    }

    // ─── Discounts & Charges (invoice level) ─────────────

    /**
     * Descuento general sobre el total bruto de la factura.
     *
     *     ->generalDiscount('Descuento cliente VIP', amount: 50.00)
     *     ->generalDiscount('10% pronto pago', rate: 10)
     */
    public function generalDiscount(string $reason, ?float $rate = null, ?float $amount = null): self
    {
        $entry = ['reason' => $reason, 'amount' => $amount ?? 0.0];

        if ($rate !== null) {
            $entry['rate'] = $rate;
        }

        $this->generalDiscounts[] = $entry;

        return $this;
    }

    /**
     * Cargo general sobre el total bruto de la factura.
     *
     *     ->generalCharge('Portes', amount: 15.00)
     */
    public function generalCharge(string $reason, ?float $rate = null, ?float $amount = null): self
    {
        $entry = ['reason' => $reason, 'amount' => $amount ?? 0.0];

        if ($rate !== null) {
            $entry['rate'] = $rate;
        }

        $this->generalCharges[] = $entry;

        return $this;
    }

    // ─── Attachments ─────────────────────────────────────

    /**
     * Adjuntar un fichero a la factura.
     *
     *     ->attach(Attachment::fromFile('/path/to/contract.pdf', 'Contrato firmado'))
     */
    public function attach(Attachment $attachment): self
    {
        $this->attachments[] = $attachment;
        return $this;
    }

    /**
     * Adjuntar un fichero directamente desde ruta.
     *
     *     ->attachFile('/path/to/contract.pdf', 'Contrato firmado')
     */
    public function attachFile(string $path, string $description, ?string $mimeType = null): self
    {
        $this->attachments[] = Attachment::fromFile($path, $description, $mimeType);
        return $this;
    }

    public function sign(InvoiceSigner $signer): self
    {
        $this->signer = $signer;
        return $this;
    }

    // ─── Output ──────────────────────────────────────────

    /** @throws InvoiceValidationException */
    public function toXml(): string
    {
        $this->validate();

        $xml = (new XmlExporter())->export($this);

        if ($this->signer !== null) {
            $xml = $this->signer->sign($xml);
        }

        return $xml;
    }

    /** @throws InvoiceValidationException */
    public function export(string $path): self
    {
        file_put_contents($path, $this->toXml());
        return $this;
    }

    private function validate(): void
    {
        $errors = (new InvoiceValidator())->validate($this);

        if (!empty($errors)) {
            throw new InvoiceValidationException($errors);
        }
    }

    // ─── Private ─────────────────────────────────────────

    private function addPayment(
        PaymentMethod $method,
        ?string       $dueDate,
        ?float        $amount,
        ?string       $iban = null,
    ): self
    {
        $this->payments[] = new Payment(
            method : $method,
            dueDate: $dueDate ? new DateTimeImmutable($dueDate) : null,
            amount : $amount,
            iban   : $iban ? str_replace(' ', '', $iban) : null,
        );

        return $this;
    }

    // ─── Getters ─────────────────────────────────────────

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getSeries(): string
    {
        return $this->series;
    }

    public function getIssueDate(): DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function getOperationDate(): ?DateTimeImmutable
    {
        return $this->operationDate;
    }

    public function getBillingPeriodStart(): ?DateTimeImmutable
    {
        return $this->billingPeriodStart;
    }

    public function getBillingPeriodEnd(): ?DateTimeImmutable
    {
        return $this->billingPeriodEnd;
    }

    public function getSchema(): Schema
    {
        return $this->schema;
    }

    public function getType(): InvoiceType
    {
        return $this->type;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getSeller(): ?Party
    {
        return $this->seller;
    }

    public function getBuyer(): ?Party
    {
        return $this->buyer;
    }

    /** @return Line[] */
    public function getLines(): array
    {
        return $this->lines;
    }

    /** @return Payment[] */
    public function getPayments(): array
    {
        return $this->payments;
    }

    public function getCorrectedNumber(): ?string
    {
        return $this->correctedNumber;
    }

    public function getCorrectedSeries(): ?string
    {
        return $this->correctedSeries;
    }

    public function getCorrectionMethod(): ?CorrectionMethod
    {
        return $this->correctionMethod;
    }

    public function getCorrectionReason(): ?CorrectionReason
    {
        return $this->correctionReason;
    }

    public function getCorrectionPeriodStart(): ?DateTimeImmutable
    {
        return $this->correctionPeriodStart;
    }

    public function getCorrectionPeriodEnd(): ?DateTimeImmutable
    {
        return $this->correctionPeriodEnd;
    }

    public function isCorrective(): bool
    {
        return $this->isCorrective;
    }

    public function getLegalLiteral(): ?string
    {
        return $this->legalLiteral;
    }

    /** @return array<int, array{reason: string, rate?: float, amount: float}> */
    public function getGeneralDiscounts(): array
    {
        return $this->generalDiscounts;
    }

    /** @return array<int, array{reason: string, rate?: float, amount: float}> */
    public function getGeneralCharges(): array
    {
        return $this->generalCharges;
    }

    /** @return Attachment[] */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function getSigner(): ?InvoiceSigner
    {
        return $this->signer;
    }
}
