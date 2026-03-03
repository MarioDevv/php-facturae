<?php
declare(strict_types=1);
namespace MarioDevv\Rex\Tests\Facturae;

use MarioDevv\Rex\Facturae\Invoice;
use MarioDevv\Rex\Facturae\Party;
use MarioDevv\Rex\Facturae\Enums\CorrectionMethod;
use MarioDevv\Rex\Facturae\Exceptions\InvoiceValidationException;
use PHPUnit\Framework\TestCase;

final class InvoiceTest extends TestCase
{
    private function validInvoice(): Invoice
    {
        return Invoice::create('FAC-001')
            ->date('2024-01-15')
            ->seller(
                Party::company('A00000000', 'Empresa Test S.L.')
                    ->address('C/ Test, 1', '28001', 'Madrid', 'Madrid')
            )
            ->buyer(
                Party::person('00000000A', 'Juan', 'Garcia', 'Lopez')
                    ->address('C/ Comprador', '08001', 'Barcelona', 'Barcelona')
            )
            ->line('Servicio de consultoria', price: 1000.00, vat: 21);
    }

    public function test_creates_valid_xml(): void
    {
        $xml = $this->validInvoice()->toXml();
        $this->assertStringContainsString('FAC-001', $xml);
        $this->assertStringContainsString('A00000000', $xml);
        $this->assertStringContainsString('Servicio de consultoria', $xml);
    }

    public function test_xml_is_valid_dom(): void
    {
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($this->validInvoice()->toXml()));
    }

    public function test_calculates_totals(): void
    {
        $xml = $this->validInvoice()->toXml();
        $this->assertStringContainsString('<TotalTaxOutputs>210.00</TotalTaxOutputs>', $xml);
        $this->assertStringContainsString('<InvoiceTotal>1210.00</InvoiceTotal>', $xml);
    }

    public function test_irpf_withheld(): void
    {
        $xml = Invoice::create('FAC-002')->date('2024-06-01')
            ->seller(
                Party::company('A00000000', 'Empresa S.L.')
                    ->address('C/ Test', '28001', 'Madrid', 'Madrid')
            )
            ->buyer(
                Party::person('00000000A', 'Ana', 'Perez')
                    ->address('C/ Otra', '28002', 'Madrid', 'Madrid')
            )
            ->line('Producto A', price: 100, quantity: 2, vat: 21)
            ->line('Servicio B', price: 500, vat: 21, irpf: 15)
            ->toXml();
        $this->assertStringContainsString('<TotalGrossAmount>700.00</TotalGrossAmount>', $xml);
        $this->assertStringContainsString('<TotalTaxOutputs>147.00</TotalTaxOutputs>', $xml);
        $this->assertStringContainsString('<TotalTaxesWithheld>75.00</TotalTaxesWithheld>', $xml);
        $this->assertStringContainsString('<InvoiceTotal>772.00</InvoiceTotal>', $xml);
    }

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

    public function test_corrective(): void
    {
        $xml = $this->validInvoice()
            ->corrects('FAC-000', 'Error en importe', CorrectionMethod::FullReplacement)
            ->toXml();
        $this->assertStringContainsString('FAC-000', $xml);
        $this->assertStringContainsString('FR', $xml);
    }

    public function test_transfer_payment(): void
    {
        $xml = $this->validInvoice()
            ->transferPayment(iban: 'ES91 2100 0418 4502 0005 1332', dueDate: '2024-02-15')
            ->toXml();
        $this->assertStringContainsString('ES9121000418450200051332', $xml);
        $this->assertStringContainsString('<PaymentMeans>04</PaymentMeans>', $xml);
    }

    public function test_discount(): void
    {
        $xml = Invoice::create('FAC-003')->date('2024-01-01')
            ->seller(Party::company('A00000000', 'T S.L.')->address('C/ T', '28001', 'Madrid', 'Madrid'))
            ->buyer(Party::person('00000000A', 'T', 'U')->address('C/ T', '28002', 'Madrid', 'Madrid'))
            ->line('Producto', price: 100, quantity: 1, vat: 21, discount: 10)
            ->toXml();
        $this->assertStringContainsString('<GrossAmount>90.00</GrossAmount>', $xml);
    }

    public function test_corporate_name(): void
    {
        $xml = $this->validInvoice()->toXml();
        $this->assertStringContainsString('<CorporateName>Empresa Test S.L.</CorporateName>', $xml);
    }

    public function test_legal_literal(): void
    {
        $xml = $this->validInvoice()
            ->legalLiteral('Exenta art. 20 LIVA')
            ->toXml();
        $this->assertStringContainsString('Exenta art. 20 LIVA', $xml);
    }

    public function test_igic(): void
    {
        $xml = Invoice::create('FAC-004')->date('2024-01-01')
            ->seller(Party::company('B76000000', 'Canaria S.L.')->address('C/ T', '35001', 'Las Palmas', 'Las Palmas'))
            ->buyer(Party::person('00000000A', 'T', 'U')->address('C/ T', '35002', 'Las Palmas', 'Las Palmas'))
            ->line('Servicio', price: 1000, igic: 7)
            ->toXml();
        $this->assertStringContainsString('<TotalTaxOutputs>70.00</TotalTaxOutputs>', $xml);
        $this->assertStringContainsString('<InvoiceTotal>1070.00</InvoiceTotal>', $xml);
    }
}
