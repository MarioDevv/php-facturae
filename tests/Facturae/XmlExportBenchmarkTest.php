<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Tests\Facturae;

use MarioDevv\Rex\Facturae\Invoice;
use MarioDevv\Rex\Facturae\Party;
use MarioDevv\Rex\Facturae\Enums\Schema;
use PHPUnit\Framework\TestCase;

final class XmlExportBenchmarkTest extends TestCase
{
    private function sampleInvoice(int $lines = 4): Invoice
    {
        $invoice = Invoice::create('BENCH-001')
            ->serie('B')
            ->date('2024-12-15')
            ->schema(Schema::V3_2_2)
            ->seller(
                Party::company('B76123456', 'Atlantic Systems S.L.')
                    ->address('C/ Triana, 52', '35002', 'Las Palmas de Gran Canaria', 'Las Palmas')
            )
            ->buyer(
                Party::company('A28000001', 'Cliente Demo S.L.')
                    ->address('C/ Gran Via, 1', '28013', 'Madrid', 'Madrid')
            );

        for ($i = 1; $i <= $lines; $i++) {
            $invoice->line("Servicio #{$i}", price: round(mt_rand(1000, 50000) / 100, 2), igic: 7);
        }

        return $invoice->transferPayment(iban: 'ES9121000418450200051332', dueDate: '2025-01-15');
    }

    public function test_single_invoice_generation(): void
    {
        $invoice = $this->sampleInvoice(4);

        $start = hrtime(true);
        $xml = $invoice->toXml();
        $elapsed = (hrtime(true) - $start) / 1_000_000;

        $this->assertNotEmpty($xml);
        fwrite(STDERR, sprintf("\n  [BENCH] 1 factura (4 lineas): %.3f ms\n", $elapsed));
    }

    public function test_invoice_with_50_lines(): void
    {
        $invoice = $this->sampleInvoice(50);

        $start = hrtime(true);
        $xml = $invoice->toXml();
        $elapsed = (hrtime(true) - $start) / 1_000_000;

        $this->assertNotEmpty($xml);
        fwrite(STDERR, sprintf("\n  [BENCH] 1 factura (50 lineas): %.3f ms\n", $elapsed));
    }

    public function test_hundred_invoices_sequentially(): void
    {
        $iterations = 100;

        $start = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $this->sampleInvoice(4)->toXml();
        }
        $elapsed = (hrtime(true) - $start) / 1_000_000;

        $avg = $elapsed / $iterations;

        $this->assertTrue(true);
        fwrite(STDERR, sprintf(
            "\n  [BENCH] %d facturas: %.1f ms total, %.3f ms/factura\n",
            $iterations, $elapsed, $avg,
        ));
    }

    public function test_memory_usage_for_large_invoice(): void
    {
        $before = memory_get_usage(true);
        $invoice = $this->sampleInvoice(200);
        $xml = $invoice->toXml();
        $after = memory_get_peak_usage(true);

        $deltaKb = ($after - $before) / 1024;

        $this->assertNotEmpty($xml);
        fwrite(STDERR, sprintf(
            "\n  [BENCH] 200 lineas: %d KB XML, %.0f KB memoria pico\n",
            strlen($xml) / 1024, $deltaKb,
        ));
    }
}
