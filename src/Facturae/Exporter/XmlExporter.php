<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Facturae\Exporter;

use DOMDocument;
use DOMElement;
use MarioDevv\Rex\Facturae\Invoice;
use MarioDevv\Rex\Facturae\Party;
use MarioDevv\Rex\Facturae\Entities\Line;
use MarioDevv\Rex\Facturae\Entities\TaxBreakdown;

final class XmlExporter
{
    private DOMDocument $dom;

    public function export(Invoice $invoice): string
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;

        $root = $this->dom->createElementNS(
            $invoice->getSchema()->xmlNamespace(),
            'fe:Facturae',
        );
        $root->setAttribute('xmlns:ds', 'http://www.w3.org/2000/09/xmldsig#');
        $this->dom->appendChild($root);

        $this->appendFileHeader($root, $invoice);
        $this->appendParties($root, $invoice);
        $this->appendInvoice($root, $invoice);

        return $this->dom->saveXML();
    }

    // ─── File Header ─────────────────────────────────────

    private function appendFileHeader(DOMElement $root, Invoice $invoice): void
    {
        $header = $this->el('FileHeader');
        $root->appendChild($header);
        $header->appendChild($this->el('SchemaVersion', $invoice->getSchema()->value));
        $header->appendChild($this->el('Modality', 'I'));

        $batch = $this->el('Batch');
        $header->appendChild($batch);

        $batchId = $invoice->getSeller()->taxNumber()
            . $invoice->getNumber()
            . $invoice->getSeries();

        $batch->appendChild($this->el('BatchIdentifier', $batchId));
        $batch->appendChild($this->el('InvoicesCount', '1'));

        $total = $this->calculateTotal($invoice);

        $tc = $this->el('TotalInvoicesAmount');
        $batch->appendChild($tc);
        $tc->appendChild($this->el('TotalAmount', $this->money($total)));

        $te = $this->el('TotalOutstandingAmount');
        $batch->appendChild($te);
        $te->appendChild($this->el('TotalAmount', $this->money($total)));

        $batch->appendChild($this->el('InvoiceCurrencyCode', $invoice->getCurrency()));
    }

    // ─── Parties ─────────────────────────────────────────

    private function appendParties(DOMElement $root, Invoice $invoice): void
    {
        $parties = $this->el('Parties');
        $root->appendChild($parties);

        $seller = $this->el('SellerParty');
        $parties->appendChild($seller);
        $this->appendParty($seller, $invoice->getSeller());

        $buyer = $this->el('BuyerParty');
        $parties->appendChild($buyer);
        $this->appendParty($buyer, $invoice->getBuyer());
    }

    private function appendParty(DOMElement $parent, Party $party): void
    {
        $taxId = $this->el('TaxIdentification');
        $parent->appendChild($taxId);
        $taxId->appendChild($this->el('PersonTypeCode', $party->isLegalEntity() ? 'J' : 'F'));
        $taxId->appendChild($this->el('ResidenceTypeCode', 'R'));
        $taxId->appendChild($this->el('TaxIdentificationNumber', $party->taxNumber()));

        if ($party->isLegalEntity()) {
            $this->appendLegalEntity($parent, $party);
        } else {
            $this->appendIndividual($parent, $party);
        }

        foreach ($party->getCentres() as $centre) {
            $el = $this->el('AdministrativeCentre');
            $parent->appendChild($el);
            $el->appendChild($this->el('CentreCode', $centre['code']));
            $el->appendChild($this->el('RoleTypeCode', $centre['role']));
            if ($centre['name'] !== null) {
                $el->appendChild($this->el('Name', $centre['name']));
            }
        }
    }

    private function appendLegalEntity(DOMElement $parent, Party $party): void
    {
        $entity = $this->el('LegalEntity');
        $parent->appendChild($entity);
        $entity->appendChild($this->el('CorporateName', $party->name()));

        if ($party->getTradeName() !== null) {
            $entity->appendChild($this->el('TradeName', $party->getTradeName()));
        }

        if ($party->getBook() !== null) {
            $reg = $this->el('RegistrationData');
            $entity->appendChild($reg);
            $this->maybe($reg, 'Book', $party->getBook());
            $this->maybe($reg, 'RegisterOfCompaniesLocation', $party->getMerchantRegister());
            $this->maybe($reg, 'Sheet', $party->getSheet());
            $this->maybe($reg, 'Folio', $party->getFolio());
            $this->maybe($reg, 'Section', $party->getSection());
            $this->maybe($reg, 'Volume', $party->getVolume());
        }

        $this->appendAddress($entity, $party);
        $this->appendContact($entity, $party);
    }

    private function appendIndividual(DOMElement $parent, Party $party): void
    {
        $individual = $this->el('Individual');
        $parent->appendChild($individual);
        $individual->appendChild($this->el('Name', $party->name()));
        $individual->appendChild($this->el('FirstSurname', $party->firstSurname() ?? ''));

        if ($party->lastSurname() !== null) {
            $individual->appendChild($this->el('SecondSurname', $party->lastSurname()));
        }

        $this->appendAddress($individual, $party);
        $this->appendContact($individual, $party);
    }

    private function appendAddress(DOMElement $parent, Party $party): void
    {
        $addr = $party->getAddress();
        if ($addr === null) {
            return;
        }

        if ($addr->countryCode === 'ESP') {
            $el = $this->el('AddressInSpain');
            $parent->appendChild($el);
            $el->appendChild($this->el('Address', $addr->street));
            $el->appendChild($this->el('PostCode', $addr->postalCode));
            $el->appendChild($this->el('Town', $addr->town));
            $el->appendChild($this->el('Province', $addr->province));
            $el->appendChild($this->el('CountryCode', $addr->countryCode));
        } else {
            $el = $this->el('OverseasAddress');
            $parent->appendChild($el);
            $el->appendChild($this->el('Address', $addr->street));
            $el->appendChild($this->el('PostCodeAndTown', $addr->postalCode . ' ' . $addr->town));
            $el->appendChild($this->el('Province', $addr->province));
            $el->appendChild($this->el('CountryCode', $addr->countryCode));
        }
    }

    private function appendContact(DOMElement $parent, Party $party): void
    {
        if ($party->getEmail() === null && $party->getPhone() === null) {
            return;
        }

        $c = $this->el('ContactDetails');
        $parent->appendChild($c);
        $this->maybe($c, 'Telephone', $party->getPhone());
        $this->maybe($c, 'TeleFax', $party->getFax());
        $this->maybe($c, 'WebAddress', $party->getWebsite());
        $this->maybe($c, 'ElectronicMail', $party->getEmail());
        $this->maybe($c, 'ContactPersons', $party->getContactPeople());
        $this->maybe($c, 'CnoCnae', $party->getCnoCnae());
        $this->maybe($c, 'INETownCode', $party->getIneTownCode());
    }

    // ─── Invoice body ────────────────────────────────────

    private function appendInvoice(DOMElement $root, Invoice $invoice): void
    {
        $invoices = $this->el('Invoices');
        $root->appendChild($invoices);

        $inv = $this->el('Invoice');
        $invoices->appendChild($inv);

        $this->appendInvoiceHeader($inv, $invoice);
        $this->appendIssueData($inv, $invoice);
        $this->appendTaxes($inv, $invoice);
        $this->appendTotals($inv, $invoice);
        $this->appendPayments($inv, $invoice);
        $this->appendItems($inv, $invoice);
        $this->appendAdditionalData($inv, $invoice);
    }

    private function appendInvoiceHeader(DOMElement $inv, Invoice $invoice): void
    {
        $header = $this->el('InvoiceHeader');
        $inv->appendChild($header);
        $header->appendChild($this->el('InvoiceNumber', $invoice->getNumber()));

        if ($invoice->getSeries() !== '') {
            $header->appendChild($this->el('InvoiceSeriesCode', $invoice->getSeries()));
        }

        $header->appendChild($this->el('InvoiceDocumentType', $invoice->getType()->value));

        if ($invoice->getCorrectedNumber() !== null) {
            $c = $this->el('Corrective');
            $header->appendChild($c);
            $c->appendChild($this->el('InvoiceNumber', $invoice->getCorrectedNumber()));
            $this->maybe($c, 'InvoiceSeriesCode', $invoice->getCorrectedSerie());
            $c->appendChild($this->el('ReasonCode', '01'));
            $c->appendChild($this->el('ReasonDescription', $invoice->getCorrectionDescription() ?? ''));
            $c->appendChild($this->el('CorrectionMethod', $invoice->getCorrectionMethod()->value));
            $c->appendChild($this->el('CorrectionMethodDescription', 'Correction'));
        }
    }

    private function appendIssueData(DOMElement $inv, Invoice $invoice): void
    {
        $d = $this->el('InvoiceIssueData');
        $inv->appendChild($d);
        $d->appendChild($this->el('IssueDate', $invoice->getIssueDate()->format('Y-m-d')));
        $d->appendChild($this->el('InvoiceCurrencyCode', $invoice->getCurrency()));
        $d->appendChild($this->el('TaxCurrencyCode', $invoice->getCurrency()));
        $d->appendChild($this->el('LanguageName', 'es'));
    }

    private function appendTaxes(DOMElement $inv, Invoice $invoice): void
    {
        $out = $this->groupTaxes($invoice->getLines(), false);
        if (!empty($out)) {
            $el = $this->el('TaxesOutputs');
            $inv->appendChild($el);
            foreach ($out as $data) {
                $this->appendTaxNode($el, $data);
            }
        }

        $held = $this->groupTaxes($invoice->getLines(), true);
        if (!empty($held)) {
            $el = $this->el('TaxesWithheld');
            $inv->appendChild($el);
            foreach ($held as $data) {
                $this->appendTaxNode($el, $data);
            }
        }
    }

    /** @param array{type: string, rate: float, base: float, amount: float} $data */
    private function appendTaxNode(DOMElement $parent, array $data): void
    {
        $tax = $this->el('Tax');
        $parent->appendChild($tax);
        $tax->appendChild($this->el('TaxTypeCode', $data['type']));
        $tax->appendChild($this->el('TaxRate', $this->money($data['rate'])));

        $base = $this->el('TaxableBase');
        $tax->appendChild($base);
        $base->appendChild($this->el('TotalAmount', $this->money($data['base'])));

        $amount = $this->el('TaxAmount');
        $tax->appendChild($amount);
        $amount->appendChild($this->el('TotalAmount', $this->money($data['amount'])));
    }

    private function appendTotals(DOMElement $inv, Invoice $invoice): void
    {
        $totals = $this->el('InvoiceTotals');
        $inv->appendChild($totals);

        $gross   = $this->grossAmount($invoice);
        $taxOut  = $this->taxAmount($invoice->getLines(), false);
        $taxWith = $this->taxAmount($invoice->getLines(), true);
        $total   = $gross + $taxOut - $taxWith;

        $totals->appendChild($this->el('TotalGrossAmount', $this->money($gross)));
        $totals->appendChild($this->el('TotalGrossAmountBeforeTaxes', $this->money($gross)));
        $totals->appendChild($this->el('TotalTaxOutputs', $this->money($taxOut)));
        $totals->appendChild($this->el('TotalTaxesWithheld', $this->money($taxWith)));
        $totals->appendChild($this->el('InvoiceTotal', $this->money($total)));
        $totals->appendChild($this->el('TotalOutstandingAmount', $this->money($total)));
        $totals->appendChild($this->el('TotalExecutableAmount', $this->money($total)));
    }

    private function appendPayments(DOMElement $inv, Invoice $invoice): void
    {
        if (empty($invoice->getPayments())) {
            return;
        }

        $details = $this->el('PaymentDetails');
        $inv->appendChild($details);

        $total = $this->calculateTotal($invoice);

        foreach ($invoice->getPayments() as $payment) {
            $inst = $this->el('Installment');
            $details->appendChild($inst);

            if ($payment->dueDate !== null) {
                $inst->appendChild(
                    $this->el('InstallmentDueDate', $payment->dueDate->format('Y-m-d'))
                );
            }

            $inst->appendChild(
                $this->el('InstallmentAmount', $this->money($payment->amount ?? $total))
            );
            $inst->appendChild(
                $this->el('PaymentMeans', $payment->method->value)
            );

            if ($payment->iban !== null) {
                $acc = $this->el('AccountToBeCredited');
                $inst->appendChild($acc);
                $acc->appendChild($this->el('IBAN', $payment->iban));
                $this->maybe($acc, 'BankCode', $payment->bic);
            }
        }
    }

    private function appendItems(DOMElement $inv, Invoice $invoice): void
    {
        $items = $this->el('Items');
        $inv->appendChild($items);

        foreach ($invoice->getLines() as $line) {
            $item = $this->el('InvoiceLine');
            $items->appendChild($item);

            $this->maybe($item, 'ArticleCode', $line->articleCode);
            $item->appendChild($this->el('ItemDescription', $line->description));
            $item->appendChild($this->el('Quantity', (string) $line->quantity));
            $item->appendChild($this->el('UnitPriceWithoutTax', $this->money($line->unitPrice)));
            $item->appendChild($this->el('TotalCost', $this->money($line->quantity * $line->unitPrice)));

            if ($line->discount !== null && $line->discount > 0) {
                $discounts = $this->el('DiscountsAndRebates');
                $item->appendChild($discounts);
                $disc = $this->el('Discount');
                $discounts->appendChild($disc);
                $disc->appendChild($this->el('DiscountReason', 'Descuento'));
                $disc->appendChild($this->el('DiscountRate', $this->money($line->discount)));
                $disc->appendChild($this->el('DiscountAmount', $this->money(
                    round($line->quantity * $line->unitPrice * $line->discount / 100, 2)
                )));
            }

            $item->appendChild($this->el('GrossAmount', $this->money($line->grossAmount())));

            // Line-level output taxes
            $outputTaxes = array_filter($line->taxes, fn(TaxBreakdown $t) => !$t->isWithholding);
            if (!empty($outputTaxes)) {
                $el = $this->el('TaxesOutputs');
                $item->appendChild($el);
                foreach ($outputTaxes as $tax) {
                    $this->appendLineTax($el, $tax, $line->grossAmount());
                }
            }

            // Line-level withheld taxes
            $withheldTaxes = array_filter($line->taxes, fn(TaxBreakdown $t) => $t->isWithholding);
            if (!empty($withheldTaxes)) {
                $el = $this->el('TaxesWithheld');
                $item->appendChild($el);
                foreach ($withheldTaxes as $tax) {
                    $this->appendLineTax($el, $tax, $line->grossAmount());
                }
            }
        }
    }

    private function appendLineTax(DOMElement $parent, TaxBreakdown $tax, float $base): void
    {
        $el = $this->el('Tax');
        $parent->appendChild($el);
        $el->appendChild($this->el('TaxTypeCode', $tax->type->value));
        $el->appendChild($this->el('TaxRate', $this->money($tax->rate)));

        $taxableBase = $this->el('TaxableBase');
        $el->appendChild($taxableBase);
        $taxableBase->appendChild($this->el('TotalAmount', $this->money($base)));

        $taxAmount = $this->el('TaxAmount');
        $el->appendChild($taxAmount);
        $taxAmount->appendChild($this->el('TotalAmount', $this->money(round($base * $tax->rate / 100, 2))));
    }

    private function appendAdditionalData(DOMElement $inv, Invoice $invoice): void
    {
        if ($invoice->getLegalLiteral() === null) {
            return;
        }

        $ad = $this->el('AdditionalData');
        $inv->appendChild($ad);
        $ad->appendChild(
            $this->el('InvoiceAdditionalInformation', $invoice->getLegalLiteral())
        );
    }

    // ─── Calculation helpers ─────────────────────────────

    /**
     * @param Line[] $lines
     * @return array<string, array{type: string, rate: float, base: float, amount: float}>
     */
    private function groupTaxes(array $lines, bool $withheld): array
    {
        $grouped = [];

        foreach ($lines as $line) {
            foreach ($line->taxes as $tax) {
                if ($tax->isWithholding !== $withheld) {
                    continue;
                }

                $key = $tax->type->value . '_' . $tax->rate;

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'type'   => $tax->type->value,
                        'rate'   => $tax->rate,
                        'base'   => 0.0,
                        'amount' => 0.0,
                    ];
                }

                $grouped[$key]['base']   += $line->grossAmount();
                $grouped[$key]['amount'] += round($line->grossAmount() * $tax->rate / 100, 2);
            }
        }

        return $grouped;
    }

    private function grossAmount(Invoice $invoice): float
    {
        return array_reduce(
            $invoice->getLines(),
            fn(float $total, Line $line) => $total + $line->grossAmount(),
            0.0,
        );
    }

    /** @param Line[] $lines */
    private function taxAmount(array $lines, bool $withheld): float
    {
        $total = 0.0;

        foreach ($lines as $line) {
            foreach ($line->taxes as $tax) {
                if ($tax->isWithholding === $withheld) {
                    $total += round($line->grossAmount() * $tax->rate / 100, 2);
                }
            }
        }

        return $total;
    }

    private function calculateTotal(Invoice $invoice): float
    {
        $gross = $this->grossAmount($invoice);

        return $gross
            + $this->taxAmount($invoice->getLines(), false)
            - $this->taxAmount($invoice->getLines(), true);
    }

    // ─── DOM helpers ─────────────────────────────────────

    private function el(string $name, ?string $value = null): DOMElement
    {
        return $value !== null
            ? $this->dom->createElement($name, htmlspecialchars($value, ENT_XML1))
            : $this->dom->createElement($name);
    }

    private function maybe(DOMElement $parent, string $name, ?string $value): void
    {
        if ($value !== null) {
            $parent->appendChild($this->el($name, $value));
        }
    }

    private function money(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }
}
