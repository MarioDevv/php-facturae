<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Tests\Facturae;

use MarioDevv\Rex\Tests\Facturae\Mother\InvoiceMother;
use PHPUnit\Framework\TestCase;

final class GenerateRealInvoiceTest extends TestCase
{
    private static string $distDir;

    public static function setUpBeforeClass(): void
    {
        self::$distDir = dirname(__DIR__, 2) . '/dist';

        if (!is_dir(self::$distDir)) {
            mkdir(self::$distDir, 0755, true);
        }
    }

    public function test_canary_igic_invoice(): void
    {
        $path = self::$distDir . '/factura-canaria-igic.xsig';
        InvoiceMother::canaryIgic()->export($path);
        $this->assertValidFacturae($path);
    }

    public function test_peninsular_irpf_invoice(): void
    {
        $path = self::$distDir . '/factura-irpf.xsig';
        InvoiceMother::withIrpf()
            ->transferPayment(iban: 'ES91 2100 0418 4502 0005 1332', dueDate: '2024-07-01')
            ->export($path);
        $this->assertValidFacturae($path);
    }

    public function test_surcharge_invoice(): void
    {
        $path = self::$distDir . '/factura-recargo-equivalencia.xsig';
        InvoiceMother::withSurcharge()->export($path);
        $this->assertValidFacturae($path);
    }

    public function test_split_payments_invoice(): void
    {
        $path = self::$distDir . '/factura-pagos-fraccionados.xsig';
        InvoiceMother::withSplitPayments()->export($path);
        $this->assertValidFacturae($path);
    }

    public function test_corrective_invoice(): void
    {
        $path = self::$distDir . '/factura-rectificativa.xsig';
        InvoiceMother::corrective()
            ->transferPayment(iban: 'ES91 2100 0418 4502 0005 1332', dueDate: '2024-03-01')
            ->export($path);
        $this->assertValidFacturae($path);
    }

    public function test_billing_period_invoice(): void
    {
        $path = self::$distDir . '/factura-periodo.xsig';
        InvoiceMother::withBillingPeriod()->export($path);
        $this->assertValidFacturae($path);
    }

    public function test_exempt_line_invoice(): void
    {
        $path = self::$distDir . '/factura-exenta.xsig';
        InvoiceMother::withExemptLine()->export($path);
        $this->assertValidFacturae($path);
    }

    public function test_units_invoice(): void
    {
        $path = self::$distDir . '/factura-unidades.xsig';
        InvoiceMother::withUnits()->export($path);
        $this->assertValidFacturae($path);
    }

    public function test_special_tax_withheld_invoice(): void
    {
        $path = self::$distDir . '/factura-ie-retenido.xsig';
        InvoiceMother::withSpecialTaxWithheld()->export($path);
        $this->assertValidFacturae($path);
    }

    public function test_custom_taxes_invoice(): void
    {
        $path = self::$distDir . '/factura-impuestos-custom.xsig';
        InvoiceMother::withCustomTaxes()->export($path);
        $this->assertValidFacturae($path);
    }

    public function test_full_featured_canary(): void
    {
        $path = self::$distDir . '/factura-completa-canaria.xsig';
        InvoiceMother::fullFeatured()->export($path);
        $this->assertValidFacturae($path);
    }

    public function test_full_featured_peninsular(): void
    {
        $path = self::$distDir . '/factura-completa-peninsular.xsig';
        InvoiceMother::fullFeaturedPeninsular()->export($path);
        $this->assertValidFacturae($path);
    }

    // ─── Assertion helper ────────────────────────────────

    private function assertValidFacturae(string $path): void
    {
        $this->assertFileExists($path);

        $content = file_get_contents($path);
        $this->assertNotEmpty($content);

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($content), 'XML is not well-formed');

        $this->assertSame(
            'http://www.facturae.gob.es/formato/Versiones/Facturaev3_2_2.xml',
            $dom->documentElement->namespaceURI,
        );

        fwrite(STDERR, sprintf(
            "\n  [DIST] %s (%s bytes)\n",
            basename($path),
            number_format(filesize($path)),
        ));
    }
}
