<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Tests\Facturae\Mother;

use MarioDevv\Rex\Facturae\Invoice;
use MarioDevv\Rex\Facturae\Party;
use MarioDevv\Rex\Facturae\Enums\CorrectionMethod;
use MarioDevv\Rex\Facturae\Enums\CorrectionReason;
use MarioDevv\Rex\Facturae\Enums\PaymentMethod;
use MarioDevv\Rex\Facturae\Enums\Schema;

final class InvoiceMother
{
    // ─── Sellers ─────────────────────────────────────────

    public static function atsys(): Party
    {
        return Party::company('B76123456', 'Atlantic Systems S.L.')
            ->tradeName('Atsys')
            ->address('C/ Triana, 52', '35002', 'Las Palmas de Gran Canaria', 'Las Palmas', 'ESP')
            ->email('info@atsys.es')
            ->phone('928000000')
            ->website('https://atsys.es');
    }

    public static function peninsularCompany(): Party
    {
        return Party::company('A00000000', 'Empresa Test S.L.')
            ->address('C/ Test, 1', '28001', 'Madrid', 'Madrid')
            ->email('admin@empresa.es')
            ->phone('910000000');
    }

    // ─── Buyers ──────────────────────────────────────────

    public static function clienteDemo(): Party
    {
        return Party::company('A28000001', 'Cliente Demo S.L.')
            ->address('C/ Gran Via, 1', '28013', 'Madrid', 'Madrid', 'ESP')
            ->email('admin@clientedemo.es');
    }

    public static function personaBuyer(): Party
    {
        return Party::person('00000000A', 'Juan', 'Garcia', 'Lopez')
            ->address('C/ Comprador, 5', '08001', 'Barcelona', 'Barcelona')
            ->email('juan@example.com');
    }

    public static function canaryBuyer(): Party
    {
        return Party::person('12345678Z', 'Ana', 'Perez', 'Rodriguez')
            ->address('C/ Mesa y Lopez, 10', '35007', 'Las Palmas de Gran Canaria', 'Las Palmas')
            ->email('ana@example.com');
    }

    // ─── Invoices ────────────────────────────────────────

    /**
     * Factura peninsular simple: 1 linea, IVA 21%, pago transferencia.
     */
    public static function simple(): Invoice
    {
        return Invoice::create('FAC-001')
            ->date('2024-01-15')
            ->seller(self::peninsularCompany())
            ->buyer(self::personaBuyer())
            ->line('Servicio de consultoria', price: 1000.00, vat: 21);
    }

    /**
     * Factura canaria con IGIC: 4 lineas, pago transferencia.
     */
    public static function canaryIgic(): Invoice
    {
        return Invoice::create('FAC-2024-0001')
            ->series('A')
            ->date('2024-12-15')
            ->schema(Schema::V3_2_2)
            ->seller(self::atsys())
            ->buyer(self::clienteDemo())
            ->line('Desarrollo web - Landing page corporativa', price: 1200.00, igic: 7)
            ->line('Mantenimiento WordPress mensual (3 meses)', price: 150.00, quantity: 3, igic: 7)
            ->line('Certificado SSL y configuracion', price: 45.50, igic: 7)
            ->line('Consultoria SEO inicial', price: 300.00, igic: 7)
            ->transferPayment(iban: 'ES91 2100 0418 4502 0005 1332', dueDate: '2025-01-15')
            ->legalLiteral('Factura exenta de IVA por aplicacion del REF Canario. IGIC aplicado al tipo general.');
    }

    /**
     * Factura con IVA + IRPF: autonomo con retencion.
     */
    public static function withIrpf(): Invoice
    {
        return Invoice::create('FAC-002')
            ->date('2024-06-01')
            ->seller(self::peninsularCompany())
            ->buyer(
                Party::person('00000000A', 'Ana', 'Perez')
                    ->address('C/ Otra, 3', '28002', 'Madrid', 'Madrid')
            )
            ->line('Producto A', price: 100, quantity: 2, vat: 21)
            ->line('Servicio B', price: 500, vat: 21, irpf: 15);
    }

    /**
     * Factura con descuento en linea.
     */
    public static function withDiscount(): Invoice
    {
        return Invoice::create('FAC-003')
            ->date('2024-01-01')
            ->seller(self::peninsularCompany())
            ->buyer(self::personaBuyer())
            ->line('Producto con descuento', price: 100, quantity: 1, vat: 21, discount: 10);
    }

    /**
     * Factura rectificativa.
     */
    public static function corrective(): Invoice
    {
        return self::simple()
            ->corrects(
                invoiceNumber: 'FAC-000',
                reason: CorrectionReason::TaxableBase,
                method: CorrectionMethod::FullReplacement,
                periodStart: '2024-01-01',
                periodEnd: '2024-03-31',
            );
    }

    /**
     * Factura con recargo de equivalencia (joyeria, retail).
     */
    public static function withSurcharge(): Invoice
    {
        return Invoice::create('FAC-005')
            ->series('R')
            ->date('2024-03-15')
            ->seller(self::peninsularCompany())
            ->buyer(self::personaBuyer())
            ->line('Anillo oro 18k', price: 450.00, vat: 21, surcharge: 5.2)
            ->line('Cadena plata', price: 85.00, vat: 21, surcharge: 5.2)
            ->cashPayment(dueDate: '2024-03-15');
    }

    /**
     * Factura con periodo de facturacion (servicio mensual).
     */
    public static function withBillingPeriod(): Invoice
    {
        return Invoice::create('FAC-006')
            ->series('S')
            ->date('2025-01-05')
            ->billingPeriod(from: '2024-12-01', to: '2024-12-31')
            ->seller(self::atsys())
            ->buyer(self::clienteDemo())
            ->line('Hosting compartido', price: 29.90, igic: 7)
            ->line('Mantenimiento WordPress', price: 150.00, igic: 7)
            ->directDebitPayment(iban: 'ES80 0049 1500 0512 3456 7890', dueDate: '2025-01-10');
    }

    /**
     * Factura con fecha de operacion distinta (devengo).
     */
    public static function withOperationDate(): Invoice
    {
        return Invoice::create('FAC-007')
            ->date('2025-01-05')
            ->operationDate('2024-12-20')
            ->seller(self::peninsularCompany())
            ->buyer(self::personaBuyer())
            ->line('Servicio entregado en diciembre', price: 750.00, vat: 21)
            ->transferPayment(iban: 'ES91 2100 0418 4502 0005 1332', dueDate: '2025-01-31');
    }

    /**
     * Factura con pagos fraccionados (3 plazos).
     */
    public static function withSplitPayments(): Invoice
    {
        return Invoice::create('FAC-008')
            ->series('F')
            ->date('2024-10-01')
            ->seller(self::atsys())
            ->buyer(self::clienteDemo())
            ->line('Desarrollo aplicacion movil', price: 6000.00, igic: 7)
            ->splitPayments(
                method: PaymentMethod::Transfer,
                installments: 3,
                firstDueDate: '2024-11-01',
                intervalDays: 30,
                iban: 'ES91 2100 0418 4502 0005 1332',
            );
    }

    /**
     * Factura con linea exenta.
     */
    public static function withExemptLine(): Invoice
    {
        return Invoice::create('FAC-009')
            ->date('2024-09-01')
            ->seller(self::peninsularCompany())
            ->buyer(self::personaBuyer())
            ->line('Consultoria tecnica', price: 800.00, vat: 21)
            ->exemptLine('Formacion bonificada FUNDAE', price: 2000.00)
            ->transferPayment(iban: 'ES91 2100 0418 4502 0005 1332', dueDate: '2024-10-01');
    }

    /**
     * Factura con pago en efectivo.
     */
    public static function cashInvoice(): Invoice
    {
        return Invoice::create('FAC-010')
            ->date('2024-07-15')
            ->seller(self::peninsularCompany())
            ->buyer(self::personaBuyer())
            ->line('Reparacion urgente', price: 120.00, vat: 21)
            ->cashPayment(dueDate: '2024-07-15');
    }

    /**
     * Factura con pago con tarjeta.
     */
    public static function cardInvoice(): Invoice
    {
        return Invoice::create('FAC-011')
            ->date('2024-08-20')
            ->seller(self::peninsularCompany())
            ->buyer(self::personaBuyer())
            ->line('Licencia software anual', price: 299.00, vat: 21)
            ->cardPayment(dueDate: '2024-08-20');
    }

    /**
     * Factura completa: todas las features posibles.
     */
    public static function fullFeatured(): Invoice
    {
        return Invoice::create('FAC-FULL-001')
            ->series('Z')
            ->date('2025-01-10')
            ->operationDate('2024-12-28')
            ->billingPeriod(from: '2024-12-01', to: '2024-12-31')
            ->schema(Schema::V3_2_2)
            ->seller(self::atsys())
            ->buyer(self::clienteDemo())
            ->line('Desarrollo web completo', price: 3500.00, igic: 7)
            ->line('Pack SEO trimestral', price: 450.00, quantity: 3, igic: 7)
            ->line('Certificados SSL (2 dominios)', price: 45.50, quantity: 2, igic: 7)
            ->exemptLine('Formacion equipo cliente (bonificada)', price: 1500.00)
            ->splitPayments(
                method: PaymentMethod::Transfer,
                installments: 3,
                firstDueDate: '2025-02-01',
                intervalDays: 30,
                iban: 'ES91 2100 0418 4502 0005 1332',
            )
            ->legalLiteral('Factura exenta de IVA por aplicacion del REF Canario. IGIC al tipo general del 7%.');
    }

    /**
     * Factura completa peninsular: IVA + IRPF + recargo + exenta + periodo + fraccionada.
     */
    public static function fullFeaturedPeninsular(): Invoice
    {
        return Invoice::create('FAC-FULL-002')
            ->series('P')
            ->date('2025-01-15')
            ->operationDate('2024-12-30')
            ->billingPeriod(from: '2024-12-01', to: '2024-12-31')
            ->schema(Schema::V3_2_2)
            ->seller(self::peninsularCompany())
            ->buyer(self::personaBuyer())
            ->line('Consultoria estrategica', price: 2000.00, vat: 21, irpf: 15)
            ->line('Material oficina', price: 150.00, quantity: 3, vat: 21, surcharge: 5.2)
            ->line('Licencia software', price: 599.00, vat: 21)
            ->exemptLine('Formacion interna art. 20 LIVA', price: 800.00)
            ->splitPayments(
                method: PaymentMethod::Transfer,
                installments: 2,
                firstDueDate: '2025-02-01',
                intervalDays: 30,
                iban: 'ES91 2100 0418 4502 0005 1332',
            )
            ->legalLiteral('Operacion sujeta a retencion de IRPF. Recargo de equivalencia aplicado segun art. 148 LIVA.');
    }

    // ─── Helpers para benchmarks ─────────────────────────

    /**
     * Factura con N lineas aleatorias para benchmarks.
     */
    public static function benchmark(int $lines = 4): Invoice
    {
        $invoice = Invoice::create('BENCH-001')
            ->series('B')
            ->date('2024-12-15')
            ->operationDate('2024-12-10')
            ->billingPeriod(from: '2024-12-01', to: '2024-12-31')
            ->schema(Schema::V3_2_2)
            ->seller(self::atsys())
            ->buyer(self::clienteDemo());

        for ($i = 1; $i <= $lines; $i++) {
            $invoice->line(
                "Servicio profesional #{$i}",
                price: round(mt_rand(1000, 50000) / 100, 2),
                igic: 7,
            );
        }

        return $invoice
            ->exemptLine('Formacion incluida', price: 500.00)
            ->splitPayments(
                method: PaymentMethod::Transfer,
                installments: 3,
                firstDueDate: '2025-01-15',
                intervalDays: 30,
                iban: 'ES91 2100 0418 4502 0005 1332',
            )
            ->legalLiteral('IGIC al tipo general. Exencion parcial por formacion.');
    }
}
