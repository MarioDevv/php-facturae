<?php

declare(strict_types=1);

namespace PhpFacturae\Tests;

use PhpFacturae\Tests\Mother\InvoiceMother;
use PHPUnit\Framework\TestCase;

final class XmlExportBenchmarkTest extends TestCase
{
    public function test_single_invoice_generation(): void
    {
        $invoice = InvoiceMother::benchmark(4);

        $start = hrtime(true);
        $xml = $invoice->toXml();
        $elapsed = (hrtime(true) - $start) / 1_000_000;

        $this->assertNotEmpty($xml);
        fwrite(STDERR, sprintf("\n  [BENCH] 1 factura (4 lineas + exempt + split): %.3f ms\n", $elapsed));
    }

    public function test_invoice_with_50_lines(): void
    {
        $invoice = InvoiceMother::benchmark(50);

        $start = hrtime(true);
        $xml = $invoice->toXml();
        $elapsed = (hrtime(true) - $start) / 1_000_000;

        $this->assertNotEmpty($xml);
        fwrite(STDERR, sprintf("\n  [BENCH] 1 factura (50 lineas + exempt + split): %.3f ms\n", $elapsed));
    }

    public function test_full_featured_invoice(): void
    {
        $start = hrtime(true);
        $xml = InvoiceMother::fullFeatured()->toXml();
        $elapsed = (hrtime(true) - $start) / 1_000_000;

        $this->assertNotEmpty($xml);
        fwrite(STDERR, sprintf("\n  [BENCH] fullFeatured canaria: %.3f ms\n", $elapsed));
    }

    public function test_full_featured_peninsular_invoice(): void
    {
        $start = hrtime(true);
        $xml = InvoiceMother::fullFeaturedPeninsular()->toXml();
        $elapsed = (hrtime(true) - $start) / 1_000_000;

        $this->assertNotEmpty($xml);
        fwrite(STDERR, sprintf("\n  [BENCH] fullFeatured peninsular (IVA+IRPF+surcharge): %.3f ms\n", $elapsed));
    }

    public function test_hundred_full_featured_invoices(): void
    {
        $iterations = 100;

        $start = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            InvoiceMother::fullFeatured()->toXml();
        }
        $elapsed = (hrtime(true) - $start) / 1_000_000;

        $this->assertTrue(true);
        fwrite(STDERR, sprintf(
            "\n  [BENCH] %d facturas fullFeatured: %.1f ms total, %.3f ms/factura\n",
            $iterations, $elapsed, $elapsed / $iterations,
        ));
    }

    public function test_memory_usage_for_large_invoice(): void
    {
        $before = memory_get_usage(true);
        $xml = InvoiceMother::benchmark(200)->toXml();
        $after = memory_get_peak_usage(true);

        $deltaKb = ($after - $before) / 1024;

        $this->assertNotEmpty($xml);
        fwrite(STDERR, sprintf(
            "\n  [BENCH] 200 lineas: %d KB XML, %.0f KB memoria pico\n",
            strlen($xml) / 1024, $deltaKb,
        ));
    }
}
