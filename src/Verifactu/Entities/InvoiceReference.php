<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Verifactu\Entities;

use DateTimeImmutable;

/**
 * Referencia a una factura anterior (usada en rectificativas y sustituciones).
 */
final class InvoiceReference
{
    public function __construct(
        private readonly string $issuerNif,
        private readonly string $invoiceNumber,
        private readonly DateTimeImmutable $issueDate,
        private readonly ?string $series = null,
    ) {}

    public function getIssuerNif(): string { return $this->issuerNif; }
    public function getInvoiceNumber(): string { return $this->invoiceNumber; }
    public function getIssueDate(): DateTimeImmutable { return $this->issueDate; }
    public function getSeries(): ?string { return $this->series; }

    public function getFullNumber(): string
    {
        if ($this->series !== null) {
            return $this->series . $this->invoiceNumber;
        }
        return $this->invoiceNumber;
    }

    public function getFormattedDate(): string
    {
        return $this->issueDate->format('d-m-Y');
    }
}
