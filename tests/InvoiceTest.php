<?php

declare(strict_types=1);

namespace PhpFacturae\Tests;

use PhpFacturae\Exceptions\InvoiceValidationException;
use PhpFacturae\Invoice;
use PhpFacturae\Party;
use PhpFacturae\Tests\Mother\InvoiceMother;
use PHPUnit\Framework\TestCase;

final class InvoiceTest extends TestCase
{
    // ─── XML generation ──────────────────────────────────

    public function test_creates_valid_xml(): void
    {
        $xml = InvoiceMother::simple()->toXml();

        $this->assertStringContainsString('FAC-001', $xml);
        $this->assertStringContainsString('A00000000', $xml);
        $this->assertStringContainsString('Servicio de consultoria', $xml);
    }

    public function test_xml_is_valid_dom(): void
    {
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML(InvoiceMother::simple()->toXml()));
    }

    // ─── Totals ──────────────────────────────────────────

    public function test_calculates_totals(): void
    {
        $xml = InvoiceMother::simple()->toXml();

        $this->assertStringContainsString('<TotalTaxOutputs>210.00</TotalTaxOutputs>', $xml);
        $this->assertStringContainsString('<InvoiceTotal>1210.00</InvoiceTotal>', $xml);
    }

    public function test_irpf_withheld(): void
    {
        $xml = InvoiceMother::withIrpf()->toXml();

        $this->assertStringContainsString('<TotalGrossAmount>700.00</TotalGrossAmount>', $xml);
        $this->assertStringContainsString('<TotalTaxOutputs>147.00</TotalTaxOutputs>', $xml);
        $this->assertStringContainsString('<TotalTaxesWithheld>75.00</TotalTaxesWithheld>', $xml);
        $this->assertStringContainsString('<InvoiceTotal>772.00</InvoiceTotal>', $xml);
    }

    public function test_discount(): void
    {
        $xml = InvoiceMother::withDiscount()->toXml();

        $this->assertStringContainsString('<GrossAmount>90.00</GrossAmount>', $xml);
    }

    public function test_igic(): void
    {
        $xml = InvoiceMother::canaryIgic()->toXml();

        $this->assertStringContainsString('<TotalTaxOutputs>139.69</TotalTaxOutputs>', $xml);
        $this->assertStringContainsString('<InvoiceTotal>2135.19</InvoiceTotal>', $xml);
    }

    // ─── Surcharge (recargo de equivalencia) ─────────────

    public function test_surcharge(): void
    {
        $xml = InvoiceMother::withSurcharge()->toXml();

        $this->assertStringContainsString('<EquivalenceSurcharge>5.20</EquivalenceSurcharge>', $xml);
        $this->assertStringContainsString('<InvoiceTotal>675.17</InvoiceTotal>', $xml);
    }

    // ─── Payment methods ─────────────────────────────────

    public function test_transfer_payment(): void
    {
        $xml = InvoiceMother::simple()
            ->transferPayment(iban: 'ES91 2100 0418 4502 0005 1332', dueDate: '2024-02-15')
            ->toXml();

        $this->assertStringContainsString('ES9121000418450200051332', $xml);
        $this->assertStringContainsString('<PaymentMeans>04</PaymentMeans>', $xml);
    }

    public function test_cash_payment(): void
    {
        $xml = InvoiceMother::cashInvoice()->toXml();

        $this->assertStringContainsString('<PaymentMeans>01</PaymentMeans>', $xml);
    }

    public function test_card_payment(): void
    {
        $xml = InvoiceMother::cardInvoice()->toXml();

        $this->assertStringContainsString('<PaymentMeans>19</PaymentMeans>', $xml);
    }

    public function test_direct_debit_payment(): void
    {
        $xml = InvoiceMother::withBillingPeriod()->toXml();

        $this->assertStringContainsString('<PaymentMeans>02</PaymentMeans>', $xml);
        $this->assertStringContainsString('<IBAN>', $xml);
    }

    public function test_split_payments(): void
    {
        $xml = InvoiceMother::withSplitPayments()->toXml();

        $this->assertStringContainsString('<InstallmentAmount>2140.00</InstallmentAmount>', $xml);
        $this->assertSame(3, substr_count($xml, '<Installment>'));
    }

    // ─── Dates & periods ─────────────────────────────────

    public function test_operation_date(): void
    {
        $xml = InvoiceMother::withOperationDate()->toXml();

        $this->assertStringContainsString('<OperationDate>2024-12-20</OperationDate>', $xml);
    }

    public function test_billing_period(): void
    {
        $xml = InvoiceMother::withBillingPeriod()->toXml();

        $this->assertStringContainsString('<StartDate>2024-12-01</StartDate>', $xml);
        $this->assertStringContainsString('<EndDate>2024-12-31</EndDate>', $xml);
    }

    // ─── Invoice types ───────────────────────────────────

    public function test_corrective(): void
    {
        $xml = InvoiceMother::corrective()->toXml();

        $this->assertStringContainsString('FAC-000', $xml);
        $this->assertStringContainsString('<InvoiceDocumentType>FC</InvoiceDocumentType>', $xml);
        $this->assertStringContainsString('<InvoiceClass>OR</InvoiceClass>', $xml);
        $this->assertStringContainsString('<ReasonCode>16</ReasonCode>', $xml);
        $this->assertStringContainsString('Base imponible', $xml);
        $this->assertStringContainsString('<StartDate>2024-01-01</StartDate>', $xml);
        $this->assertStringContainsString('<CorrectionMethod>01</CorrectionMethod>', $xml);
    }

    // ─── Exempt lines & SpecialTaxableEvent ──────────────

    public function test_exempt_line(): void
    {
        $xml = InvoiceMother::withExemptLine()->toXml();

        $this->assertStringContainsString('Formacion bonificada FUNDAE', $xml);
        $this->assertStringContainsString('<TotalTaxOutputs>168.00</TotalTaxOutputs>', $xml);
        $this->assertStringContainsString('<InvoiceTotal>2968.00</InvoiceTotal>', $xml);
        $this->assertStringContainsString('<SpecialTaxableEventCode>02</SpecialTaxableEventCode>', $xml);
        $this->assertStringContainsString('art. 20.Uno.9 LIVA', $xml);
    }

    public function test_custom_taxes_with_special_taxable_event(): void
    {
        $xml = InvoiceMother::withCustomTaxes()->toXml();

        $this->assertStringContainsString('<TaxTypeCode>18</TaxTypeCode>', $xml); // REIGIC
        $this->assertStringContainsString('<SpecialTaxableEventCode>02</SpecialTaxableEventCode>', $xml);
        $this->assertStringContainsString('art. 20 LIVA', $xml);
    }

    // ─── Unit of Measure ─────────────────────────────────

    public function test_unit_of_measure(): void
    {
        $xml = InvoiceMother::withUnits()->toXml();

        $this->assertStringContainsString('<UnitOfMeasure>02</UnitOfMeasure>', $xml); // Hours
        $this->assertStringContainsString('<UnitOfMeasure>36</UnitOfMeasure>', $xml); // KWh
        $this->assertStringContainsString('<UnitOfMeasure>06</UnitOfMeasure>', $xml); // Boxes
    }

    // ─── isWithheld override ─────────────────────────────

    public function test_special_tax_withheld(): void
    {
        $xml = InvoiceMother::withSpecialTaxWithheld()->toXml();

        // IE (07) should appear in TaxesWithheld, not TaxesOutputs
        $this->assertStringContainsString('<TaxesWithheld>', $xml);
        $this->assertStringContainsString('<TaxTypeCode>07</TaxTypeCode>', $xml); // IE
        // IVA 21% on 500 = 105, IE 4% withheld on 500 = 20 => Total = 500 + 105 - 20 = 585
        $this->assertStringContainsString('<InvoiceTotal>585.00</InvoiceTotal>', $xml);
    }

    // ─── Misc ────────────────────────────────────────────

    public function test_corporate_name(): void
    {
        $xml = InvoiceMother::simple()->toXml();

        $this->assertStringContainsString('<CorporateName>Empresa Test S.L.</CorporateName>', $xml);
    }

    public function test_legal_literal(): void
    {
        $xml = InvoiceMother::simple()
            ->legalLiteral('Exenta art. 20 LIVA')
            ->toXml();

        $this->assertStringContainsString('Exenta art. 20 LIVA', $xml);
    }

    // ─── Validation ──────────────────────────────────────

    public function test_fails_without_seller(): void
    {
        $this->expectException(InvoiceValidationException::class);

        Invoice::create('FAC-001')
            ->buyer(Party::person('00000000A', 'J', 'G')->address('C/', '28001', 'Madrid', 'Madrid'))
            ->line('Test', price: 100, vat: 21)
            ->toXml();
    }

    public function test_fails_without_lines(): void
    {
        $this->expectException(InvoiceValidationException::class);

        Invoice::create('FAC-001')
            ->seller(Party::company('A00000000', 'T')->address('C/', '28001', 'Madrid', 'Madrid'))
            ->buyer(Party::person('00000000A', 'J', 'G')->address('C/', '28001', 'Madrid', 'Madrid'))
            ->toXml();
    }

    // ─── Full featured ───────────────────────────────────

    public function test_full_featured_generates_valid_xml(): void
    {
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML(InvoiceMother::fullFeatured()->toXml()));
    }

    public function test_full_featured_peninsular_generates_valid_xml(): void
    {
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML(InvoiceMother::fullFeaturedPeninsular()->toXml()));
    }
}
