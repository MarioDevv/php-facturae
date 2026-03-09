<?php

declare(strict_types=1);

namespace PhpFacturae\Validation;

use PhpFacturae\Invoice;

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
            $errors[] = 'At least one invoice line is required.';
        }

        if ($invoice->getNumber() === '') {
            $errors[] = 'Invoice number is required.';
        }

        if ($invoice->getBillingPeriodStart() !== null xor $invoice->getBillingPeriodEnd() !== null) {
            $errors[] = 'Billing period requires both start and end dates.';
        }

        if (
            $invoice->getBillingPeriodStart() !== null
            && $invoice->getBillingPeriodEnd() !== null
            && $invoice->getBillingPeriodStart() > $invoice->getBillingPeriodEnd()
        ) {
            $errors[] = 'Billing period start date must be before end date.';
        }

        return $errors;
    }
}
