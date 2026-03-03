<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Facturae;

use DateTimeImmutable;
use MarioDevv\Rex\Facturae\Entities\Line;
use MarioDevv\Rex\Facturae\Entities\Payment;
use MarioDevv\Rex\Facturae\Entities\TaxBreakdown;
use MarioDevv\Rex\Facturae\Enums\CorrectionMethod;
use MarioDevv\Rex\Facturae\Enums\InvoiceType;
use MarioDevv\Rex\Facturae\Enums\PaymentMethod;
use MarioDevv\Rex\Facturae\Enums\Schema;
use MarioDevv\Rex\Facturae\Enums\Tax;
use MarioDevv\Rex\Facturae\Exceptions\InvoiceValidationException;
use MarioDevv\Rex\Facturae\Exporter\XmlExporter;
use MarioDevv\Rex\Facturae\Signer\InvoiceSigner;
use MarioDevv\Rex\Facturae\Validation\InvoiceValidator;

final class Invoice
{
    private string            $number;
    private string            $series = '';
    private DateTimeImmutable $issueDate;
    private Schema            $schema = Schema::V3_2_2;
    private InvoiceType       $type = InvoiceType::Full;
    private string            $currency = 'EUR';
    private ?string           $description = null;
    private ?Party            $seller = null;
    private ?Party            $buyer = null;
    /** @var Line[] */
    private array             $lines = [];
    /** @var Payment[] */
    private array             $payments = [];
    private ?string           $correctedNumber = null;
    private ?string           $correctedSerie = null;
    private ?CorrectionMethod $correctionMethod = null;
    private ?string           $correctionDescription = null;
    private ?string           $legalLiteral = null;
    private ?InvoiceSigner    $signer = null;

    private function __construct(string $number)
    {
        $this->number = $number;
        $this->issueDate = new DateTimeImmutable();
    }

    public static function create(string $number): self
    {
        return new self($number);
    }

    // ─── Fluent API ──────────────────────────────────────

    public function serie(string $serie): self
    {
        $this->series = $serie;
        return $this;
    }

    public function date(string|DateTimeImmutable $date): self
    {
        $this->issueDate = is_string($date) ? new DateTimeImmutable($date) : $date;
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

    /**
     * Add a line item with Spanish tax shortcuts.
     *
     *     ->line('Lampara de pie', quantity: 3, price: 20.14, vat: 21)
     *     ->line('Consultoria', price: 500, vat: 21, irpf: 15)
     *     ->line('Producto Canarias', price: 100, igic: 7)
     */
    public function line(
        string  $description,
        float   $price,
        float   $quantity = 1,
        ?float  $vat = null,
        ?float  $irpf = null,
        ?float  $igic = null,
        ?float  $discount = null,
        ?string $articleCode = null,
        ?string $detailedDescription = null,
    ): self {
        $taxes = [];
        if ($vat !== null)  { $taxes[] = new TaxBreakdown(Tax::IVA, $vat); }
        if ($irpf !== null) { $taxes[] = new TaxBreakdown(Tax::IRPF, $irpf, isWithholding: true); }
        if ($igic !== null) { $taxes[] = new TaxBreakdown(Tax::IGIC, $igic); }

        $this->lines[] = new Line(
            description: $description,
            quantity: $quantity,
            unitPrice: $price,
            taxes: $taxes,
            articleCode: $articleCode,
            discount: $discount,
            detailedDescription: $detailedDescription,
        );

        return $this;
    }

    /**
     * Add a line with custom tax configuration.
     *
     * @param TaxBreakdown[] $taxes
     */
    public function customLine(
        string  $description,
        float   $price,
        array   $taxes,
        float   $quantity = 1,
        ?float  $discount = null,
        ?string $articleCode = null,
    ): self {
        $this->lines[] = new Line(
            description: $description,
            quantity: $quantity,
            unitPrice: $price,
            taxes: $taxes,
            articleCode: $articleCode,
            discount: $discount,
        );

        return $this;
    }

    public function payment(Payment $payment): self
    {
        $this->payments[] = $payment;
        return $this;
    }

    /**
     * Shorthand: transfer payment.
     *
     *     ->transferPayment(iban: 'ES00 0000 0000 0000 0000', dueDate: '2024-12-31')
     */
    public function transferPayment(
        string  $iban,
        ?string $dueDate = null,
        ?float  $amount = null,
    ): self {
        $this->payments[] = new Payment(
            method: PaymentMethod::Transfer,
            dueDate: $dueDate ? new DateTimeImmutable($dueDate) : null,
            amount: $amount,
            iban: str_replace(' ', '', $iban),
        );

        return $this;
    }

    /**
     * Mark as corrective invoice (rectificativa).
     */
    public function corrects(
        string           $invoiceNumber,
        string           $reason,
        CorrectionMethod $method = CorrectionMethod::FullReplacement,
        ?string          $serie = null,
    ): self {
        $this->type = InvoiceType::CorrectedFull;
        $this->correctedNumber = $invoiceNumber;
        $this->correctedSerie = $serie;
        $this->correctionMethod = $method;
        $this->correctionDescription = $reason;

        return $this;
    }

    public function legalLiteral(string $literal): self
    {
        $this->legalLiteral = $literal;
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

    // ─── Getters (for Exporter/Validator) ────────────────

    public function getNumber(): string                          { return $this->number; }
    public function getSeries(): string                           { return $this->series; }
    public function getIssueDate(): DateTimeImmutable            { return $this->issueDate; }
    public function getSchema(): Schema                          { return $this->schema; }
    public function getType(): InvoiceType                       { return $this->type; }
    public function getCurrency(): string                        { return $this->currency; }
    public function getDescription(): ?string                    { return $this->description; }
    public function getSeller(): ?Party                          { return $this->seller; }
    public function getBuyer(): ?Party                           { return $this->buyer; }
    /** @return Line[] */
    public function getLines(): array                            { return $this->lines; }
    /** @return Payment[] */
    public function getPayments(): array                         { return $this->payments; }
    public function getCorrectedNumber(): ?string                { return $this->correctedNumber; }
    public function getCorrectedSerie(): ?string                 { return $this->correctedSerie; }
    public function getCorrectionMethod(): ?CorrectionMethod     { return $this->correctionMethod; }
    public function getCorrectionDescription(): ?string          { return $this->correctionDescription; }
    public function getLegalLiteral(): ?string                    { return $this->legalLiteral; }
    public function getSigner(): ?InvoiceSigner                  { return $this->signer; }
}
