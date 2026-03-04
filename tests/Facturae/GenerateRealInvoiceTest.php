<?php
declare(strict_types=1);

namespace MarioDevv\Rex\Tests\Facturae;

use MarioDevv\Rex\Facturae\Invoice;
use MarioDevv\Rex\Facturae\Party;
use MarioDevv\Rex\Facturae\Enums\Schema;
use PHPUnit\Framework\TestCase;

final class GenerateRealInvoiceTest extends TestCase
{
    public function test_it_generates_a_valid_facturae_xml(): void
    {
        $distDir = dirname(__DIR__, 2) . '/dist';
        if (!is_dir($distDir)) {
            mkdir($distDir, 0755, true);
        }

        $invoice = Invoice::create('FAC-2024-0001')
            ->serie('A')
            ->date('2024-12-15')
            ->schema(Schema::V3_2_2)
            ->seller(
                Party::company('B76123456', 'Atlantic Systems S.L.')
                    ->tradeName('Atsys')
                    ->address('C/ Triana, 52', '35002', 'Las Palmas de Gran Canaria', 'Las Palmas', 'ESP')
                    ->email('info@atsys.es')
                    ->phone('928000000')
            )
            ->buyer(
                Party::company('A28000001', 'Cliente Demo S.L.')
                    ->address('C/ Gran Via, 1', '28013', 'Madrid', 'Madrid', 'ESP')
                    ->email('admin@clientedemo.es')
            )
            ->line('Desarrollo web - Landing page corporativa', price: 1200.00, igic: 7)
            ->line('Mantenimiento WordPress mensual (3 meses)', price: 150.00, quantity: 3, igic: 7)
            ->line('Certificado SSL y configuracion', price: 45.50, igic: 7)
            ->line('Consultoria SEO inicial', price: 300.00, igic: 7)
            ->transferPayment(
                iban   : 'ES91 2100 0418 4502 0005 1332',
                dueDate: '2025-01-15',
            )
            ->legalLiteral('Factura exenta de IVA por aplicacion del REF Canario. IGIC aplicado al tipo general.');

        $path = $distDir . '/factura-sample.xsig';
        $invoice->export($path);

        $this->assertFileExists($path);

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML(file_get_contents($path)));

        $this->assertSame(
            'http://www.facturae.gob.es/formato/Versiones/Facturaev3_2_2.xml',
            $dom->documentElement->namespaceURI,
        );
    }
}
