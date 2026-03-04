<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Verifactu\Entities;

use DateTimeImmutable;

/**
 * Datos del registro anterior para el encadenamiento de hashes.
 */
final class PreviousHash
{
    public function __construct(
        private readonly string $issuerNif,
        private readonly string $invoiceNumber,
        private readonly DateTimeImmutable $issueDate,
        private readonly string $hash,
    ) {}

    public function getIssuerNif(): string { return $this->issuerNif; }
    public function getInvoiceNumber(): string { return $this->invoiceNumber; }
    public function getIssueDate(): DateTimeImmutable { return $this->issueDate; }
    public function getHash(): string { return $this->hash; }
    public function getFormattedDate(): string { return $this->issueDate->format('d-m-Y'); }
}
