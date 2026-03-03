<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Facturae\Validation;

use MarioDevv\Rex\Facturae\Invoice;

final class InvoiceValidator
{
    /** @return string[] */
    public function validate(Invoice $invoice): array
    {
        $errors = [];

        if ($invoice->getSeller() === null) {
            $errors[] = 'Seller is required.';
        } elseif ($invoice->getSeller()->getAddress() === null) {
            $errors[] = 'Seller address is required.';
        }

        if ($invoice->getBuyer() === null) {
            $errors[] = 'Buyer is required.';
        } elseif ($invoice->getBuyer()->getAddress() === null) {
            $errors[] = 'Buyer address is required.';
        }

        if (empty($invoice->getLines())) {
            $errors[] = 'At least one line item is required.';
        }

        if ($invoice->getNumber() === '') {
            $errors[] = 'Invoice number is required.';
        }

        $isCorrective = in_array($invoice->getType()->value, ['FR', 'FS'], true);

        if ($isCorrective) {
            if ($invoice->getCorrectedNumber() === null) {
                $errors[] = 'Corrective invoices must reference the corrected invoice number.';
            }
            if ($invoice->getCorrectionMethod() === null) {
                $errors[] = 'Corrective invoices must specify a correction method.';
            }
        }

        return $errors;
    }
}
