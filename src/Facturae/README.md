# Rex

[![Tests](https://github.com/MarioDevv/rex/workflows/Tests/badge.svg)](https://github.com/MarioDevv/rex/actions)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.2-8892BF)](composer.json)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

Genera, firma y exporta facturas electrónicas en formato [FacturaE](http://www.facturae.gob.es/) con una API fluent y type-safe.

```php
Invoice::create('FAC-001')
    ->series('A')
    ->date('2025-03-01')
    ->seller(Party::company('B12345678', 'Mi Empresa S.L.')->address('C/ Mayor 10', '28013', 'Madrid', 'Madrid'))
    ->buyer(Party::person('12345678Z', 'Laura', 'Gómez', 'Ruiz')->address('C/ Sol 3', '28012', 'Madrid', 'Madrid'))
    ->line('Diseño logotipo', price: 450.00, vat: 21)
    ->transferPayment(iban: 'ES91 2100 0418 4502 0005 1332', dueDate: '2025-03-31')
    ->sign(Signer::pfx('certificado.pfx', 'password'))
    ->export('factura.xsig');
```

## Highlights

- **Fluent API con enums** — nada de arrays asociativos ni constantes sueltas
- **Firma XAdES-EPES** — con sellado de tiempo TSA incluido (PKCS#12 y PEM)
- **Validado contra XSD** — FacturaE 3.2, 3.2.1 y 3.2.2
- **Cero dependencias** — solo extensiones estándar de PHP (`openssl`, `dom`)
- **29 impuestos, 19 métodos de pago, 36 unidades de medida**
- **~0.2 ms** por factura simple, ~22 ms para 100 facturas

## Instalación

```bash
composer require mariodevv/rex
```

Requiere PHP 8.2+ con `ext-openssl` (firma), `ext-dom` (XML) y `ext-curl` (TSA, opcional).

## Uso

### Líneas e impuestos

```php
->line('Producto', price: 100, vat: 21)                          // IVA 21%
->line('Servicio profesional', price: 500, vat: 21, irpf: 15)    // IVA + retención IRPF
->line('Producto canario', price: 100, igic: 7)                  // IGIC 7%
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
Party::company('B12345678', 'Empresa S.L.')         // Persona jurídica
    ->tradeName('Nombre Comercial')
    ->address('C/ Mayor 10', '28013', 'Madrid', 'Madrid')
    ->email('admin@empresa.es')
    ->merchantRegister(book: '1', register: 'Madrid', sheet: 'T-12345', folio: '100')

Party::person('12345678Z', 'Laura', 'Gómez', 'Ruiz')   // Persona física
    ->address('C/ Sol 3', '28012', 'Madrid', 'Madrid')

Party::company('FR12345678901', 'Entreprise SAS')        // Extranjero
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

### Firma

```php
->sign(Signer::pfx('certificado.pfx', 'password'))                                      // PKCS#12
->sign(Signer::pem('cert.pem', 'key.pem'))                                              // PEM
->sign(Signer::pfx('certificado.pfx', 'password')->timestamp('https://freetsa.org/tsr')) // + TSA
```

También firma XMLs generados por otros programas:

```php
$signedXml = Pkcs12Signer::pfx('cert.pfx', 'pass')->sign(file_get_contents('factura.xml'));
```

### Otros

```php
->schema(Schema::V3_2_2)                                  // Versión XSD (default 3.2.2)
->operationDate('2025-02-28')                              // Fecha operación
->billingPeriod(from: '2025-02-01', to: '2025-02-28')     // Periodo facturación
```

## Validación

Los XMLs se validan contra el [XSD oficial 3.2.2](http://www.facturae.gob.es/formato/Paginas/version-3-2.aspx) y en el [validador de FACe](https://face.gob.es/es/facturas/validar-visualizar-factura):

```bash
xmllint --schema Facturaev3_2_2.xsd dist/factura-completa.xsig --noout
```

## Roadmap

- [x] XML FacturaE 3.2 / 3.2.1 / 3.2.2
- [x] 29 impuestos · 19 métodos de pago · 36 unidades
- [x] Multi-impuesto por línea, recargo equivalencia
- [x] Operaciones exentas y no sujetas
- [x] Rectificativas (22 motivos + periodo fiscal)
- [x] Firma XAdES-EPES + sellado de tiempo TSA
- [x] Descuentos y cargos generales
- [x] Adjuntos embebidos
- [x] Pagos fraccionados
- [x] Personas físicas/jurídicas, extranjeros, centros FACe
- [ ] Envío a FACe (AAPP)
- [ ] Envío a FACeB2B
- [ ] Suplidos
- [ ] Cesionarios (factoring)
- [ ] Terceros (third-party issuer)
- [ ] Precisión configurable (redondeo por línea vs factura)

## Estructura del proyecto

```
src/Facturae/
├── Entities/       Address, Attachment, Line, Payment, TaxBreakdown
├── Enums/          Tax (29), PaymentMethod (19), UnitOfMeasure (36),
│                   CorrectionReason (22), CorrectionMethod, Schema, ...
├── Exceptions/     InvoiceValidationException, InvalidPostalCodeException
├── Exporter/       XmlExporter
├── Signer/         InvoiceSigner (interface), Pkcs12Signer
├── Validation/     InvoiceValidator
├── Invoice.php     Entry point
├── Party.php       Emisor / receptor
└── Signer.php      Facade

tests/Facturae/
├── Mother/         InvoiceMother (14 escenarios Object Mother)
├── InvoiceTest.php
├── LineTest.php
├── PartyTest.php
├── GenerateRealInvoiceTest.php
└── XmlExportBenchmarkTest.php
```

## Inspiración

Rex nace como alternativa moderna a [Facturae-PHP](https://github.com/josemmo/Facturae-PHP) de josemmo, referencia durante años para la facturación electrónica en PHP. Si buscas una solución madura y probada en producción, su librería es una apuesta segura.

## Contribuir

```bash
git clone https://github.com/MarioDevv/rex.git
cd rex
composer install
vendor/bin/phpunit
```

¿Encontraste un bug? [Abre un issue](https://github.com/MarioDevv/rex/issues).
¿Quieres aportar código? Los PRs son bienvenidos.

## Licencia

[MIT](LICENSE)
