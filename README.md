# PhpFacturae

[![Tests](https://github.com/php-facturae/php-facturae/actions/workflows/php.yml/badge.svg?branch=master)](https://github.com/php-facturae/php-facturae/actions/workflows/php.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/php-facturae/php-facturae.svg)](https://packagist.org/packages/php-facturae/php-facturae)
[![Total Downloads](https://img.shields.io/packagist/dt/php-facturae/php-facturae.svg)](https://packagist.org/packages/php-facturae/php-facturae)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.2-8892BF)](composer.json)
[![PHPStan](https://img.shields.io/badge/phpstan-level%208-brightgreen)](phpstan.neon)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

Librería PHP moderna para generar, firmar y exportar **facturas electrónicas** en formato [FacturaE](http://www.facturae.gob.es/) (3.2, 3.2.1, 3.2.2) con firma **XAdES-EPES** — sin dependencias externas.

> La alternativa moderna a [Facturae-PHP](https://github.com/josemmo/Facturae-PHP): API fluent con named arguments, enums nativos de PHP 8.2+, tipado estricto y PHPStan nivel 8.

```php
use PhpFacturae\Invoice;
use PhpFacturae\Party;
use PhpFacturae\Signer;

Invoice::create('FAC-001')
    ->series('A')
    ->date('2025-03-01')
    ->seller(Party::company('B12345674', 'Mi Empresa S.L.')
        ->address('C/ Mayor 10', '28013', 'Madrid', 'Madrid'))
    ->buyer(Party::person('12345678Z', 'Laura', 'Gómez', 'Ruiz')
        ->address('C/ Sol 3', '28012', 'Madrid', 'Madrid'))
    ->line('Diseño logotipo', price: 450.00, vat: 21)
    ->transferPayment(iban: 'ES91 2100 0418 4502 0005 1332', dueDate: '2025-03-31')
    ->sign(Signer::pfx('certificado.pfx', 'password'))
    ->export('factura.xsig');
```

## ¿Por qué PhpFacturae?

| | **PhpFacturae** | josemmo/facturae-php |
|---|---|---|
| API | Fluent con named arguments | Arrays asociativos |
| PHP mínimo | 8.2+ con enums y readonly | 5.6+ |
| Tipado | Estricto · PHPStan nivel 8 | Sin análisis estático |
| Impuestos | Named args: `vat:`, `igic:`, `irpf:` | Constantes: `TAX_IVA` + arrays |
| Dependencias | Cero (solo ext-dom, ext-openssl) | Cero |
| Rendimiento | ~0.2 ms / factura | — |

## Instalación

```bash
composer require php-facturae/php-facturae
```

Requiere **PHP 8.2+** con `ext-openssl` (firma), `ext-dom` (XML) y `ext-curl` (TSA, opcional).

## Uso rápido

### Líneas e impuestos

```php
->line('Producto', price: 100, vat: 21)                          // IVA 21 %
->line('Servicio profesional', price: 500, vat: 21, irpf: 15)    // IVA + retención IRPF
->line('Producto canario', price: 100, igic: 7)                  // IGIC 7 %
->line('Joyería', price: 200, vat: 21, surcharge: 5.2)           // IVA + recargo equivalencia
->exemptLine('Formación', price: 2000, reason: 'Art. 20.Uno.9')  // Exenta
```

Para combinaciones más específicas, `customLine` acepta un array de `TaxBreakdown`:

```php
->customLine('Producto canario', price: 300, taxes: [
    new TaxBreakdown(Tax::IGIC, 7),
    new TaxBreakdown(Tax::REIGIC, 0.5),
])
```

### Partes (emisor / receptor)

```php
// Persona jurídica
Party::company('B12345674', 'Empresa S.L.')
    ->tradeName('Nombre Comercial')
    ->address('C/ Mayor 10', '28013', 'Madrid', 'Madrid')
    ->email('admin@empresa.es')
    ->merchantRegister(book: '1', register: 'Madrid', sheet: 'T-12345', folio: '100')

// Persona física
Party::person('12345678Z', 'Laura', 'Gómez', 'Ruiz')
    ->address('C/ Sol 3', '28012', 'Madrid', 'Madrid')

// Extranjero
Party::company('FR12345678901', 'Entreprise SAS')
    ->address('12 Rue de la Paix', '75002', 'Paris', 'Île-de-France', 'FRA')
```

Centros administrativos para FACe:

```php
Party::company('S2800000A', 'Ministerio de Ejemplo')
    ->address('C/ Oficial 1', '28001', 'Madrid', 'Madrid')
    ->centre('01', 'L01234567', 'Oficina contable')
    ->centre('02', 'L01234567', 'Órgano gestor')
    ->centre('03', 'L01234567', 'Unidad tramitadora')
```

### Pagos

```php
->transferPayment(iban: 'ES91...', dueDate: '2025-04-01')
->cashPayment(dueDate: '2025-03-01')
->cardPayment(dueDate: '2025-03-01')
->directDebitPayment(iban: 'ES80...', dueDate: '2025-03-10')
```

Pagos fraccionados (divide el total en N plazos, ajusta céntimos en el último):

```php
->splitPayments(
    method: PaymentMethod::Transfer,
    installments: 3,
    firstDueDate: '2025-04-01',
    intervalDays: 30,
    iban: 'ES91...',
)
```

### Descuentos y cargos

```php
->generalDiscount('Cliente VIP', rate: 5)
->generalDiscount('Promoción', amount: 50.00)
->generalCharge('Portes', amount: 15.00)
```

### Rectificativas

```php
->corrects(
    invoiceNumber: 'FAC-001',
    reason: CorrectionReason::TaxableBase,
    method: CorrectionMethod::FullReplacement,
    series: 'A',
    periodStart: '2025-01-01',
    periodEnd: '2025-03-31',
)
```

22 motivos en `CorrectionReason`, 4 métodos en `CorrectionMethod`.

### Adjuntos

```php
->attachFile('/path/to/contrato.pdf', 'Contrato firmado')
->attach(Attachment::fromData($rawPdf, 'application/pdf', 'Albarán'))
```

### Firma electrónica XAdES

```php
// PKCS#12
->sign(Signer::pfx('certificado.pfx', 'password'))

// PEM
->sign(Signer::pem('cert.pem', 'key.pem'))

// Con sellado de tiempo TSA
->sign(Signer::pfx('certificado.pfx', 'password')->timestamp('https://freetsa.org/tsr'))
```

También firma XMLs generados por otros programas:

```php
$signedXml = Pkcs12Signer::pfx('cert.pfx', 'pass')->sign(file_get_contents('factura.xml'));
```

### Otros

```php
->schema(Schema::V3_2_2)                                  // Versión XSD (por defecto 3.2.2)
->operationDate('2025-02-28')                              // Fecha operación (devengo)
->billingPeriod(from: '2025-02-01', to: '2025-02-28')     // Periodo facturación
->legalLiteral('Factura exenta de IVA por aplicación del REF Canario.')
```

## Features

- [x] XML FacturaE 3.2 / 3.2.1 / 3.2.2
- [x] Firma XAdES-EPES + sellado de tiempo TSA
- [x] 29 impuestos · 19 métodos de pago · 36 unidades de medida
- [x] Multi-impuesto por línea, recargo de equivalencia
- [x] Operaciones exentas y no sujetas
- [x] Facturas rectificativas (22 motivos + periodo fiscal)
- [x] Descuentos y cargos generales
- [x] Adjuntos embebidos (PDF, imágenes, etc.)
- [x] Pagos fraccionados con ajuste de céntimos
- [x] Personas físicas / jurídicas / extranjeros
- [x] Centros administrativos FACe (DIR3)
- [x] PHPStan nivel 8 · CI con PHP 8.2, 8.3 y 8.4
- [ ] Envío directo a FACe (AAPP)
- [ ] Envío a FACeB2B
- [ ] Suplidos
- [ ] Cesionarios (factoring)
- [ ] Terceros (third-party issuer)

## Contribuir

```bash
git clone https://github.com/php-facturae/php-facturae.git
cd php-facturae
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse src --level=8
```

¿Encontraste un bug? [Abre un issue](https://github.com/php-facturae/php-facturae/issues).
¿Quieres aportar código? Los PRs son bienvenidos.

## Licencia

[MIT](LICENSE) © Mario Pérez
