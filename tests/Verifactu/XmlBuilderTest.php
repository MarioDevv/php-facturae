<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Tests\Verifactu;

use DOMDocument;
use DOMXPath;
use MarioDevv\Rex\Tests\Verifactu\Mother\RecordMother;
use PHPUnit\Framework\TestCase;

final class XmlBuilderTest extends TestCase
{
    private function xpath(string $xml): DOMXPath
    {
        $dom = new DOMDocument();
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('sf', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV/cont/ws/SuministroInformacion.xsd');
        $xpath->registerNamespace('sum', 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV/cont/ws/SuministroLR.xsd');

        return $xpath;
    }

    private function val(DOMXPath $xpath, string $query): string
    {
        $nodes = $xpath->query($query);
        if ($nodes === false || $nodes->length === 0) {
            return '';
        }
        return $nodes->item(0)->nodeValue ?? '';
    }

    // -------------------------------------------------------------------------
    // Alta structure
    // -------------------------------------------------------------------------

    public function test_alta_root_element_is_reg_factu(): void
    {
        $xml  = RecordMother::simpleAlta()->toXml(RecordMother::computerSystem());
        $dom  = new DOMDocument();
        $dom->loadXML($xml);

        self::assertSame('sum:RegFactuSistemaFacturacion', $dom->documentElement->nodeName);
    }

    public function test_alta_contains_id_factura_block(): void
    {
        $xml   = RecordMother::simpleAlta()->toXml(RecordMother::computerSystem());
        $xpath = $this->xpath($xml);

        self::assertSame(RecordMother::ISSUER_NIF, $this->val($xpath, '//sf:IDFactura/sf:IDEmisorFactura'));
        self::assertSame('AFAC-001', $this->val($xpath, '//sf:IDFactura/sf:NumSerieFactura'));
        self::assertSame('15-06-2025', $this->val($xpath, '//sf:IDFactura/sf:FechaExpedicionFactura'));
    }

    public function test_alta_contains_tipo_factura(): void
    {
        $xml   = RecordMother::simpleAlta()->toXml(RecordMother::computerSystem());
        $xpath = $this->xpath($xml);

        self::assertSame('F1', $this->val($xpath, '//sf:TipoFactura'));
    }

    public function test_alta_contains_descripcion_operacion(): void
    {
        $xml   = RecordMother::simpleAlta()->toXml(RecordMother::computerSystem());
        $xpath = $this->xpath($xml);

        self::assertSame('Servicios de consultoría', $this->val($xpath, '//sf:DescripcionOperacion'));
    }

    public function test_alta_contains_desglose_iva(): void
    {
        $xml   = RecordMother::simpleAlta()->toXml(RecordMother::computerSystem());
        $xpath = $this->xpath($xml);

        self::assertSame('01', $this->val($xpath, '//sf:DetalleIVA/sf:Impuesto'));
        self::assertSame('21.00', $this->val($xpath, '//sf:DetalleIVA/sf:TipoImpositivo'));
        self::assertSame('2500.00', $this->val($xpath, '//sf:DetalleIVA/sf:BaseImponible'));
        self::assertSame('525.00', $this->val($xpath, '//sf:DetalleIVA/sf:CuotaRepercutida'));
    }

    public function test_alta_contains_cuota_total_and_importe_total(): void
    {
        $xml   = RecordMother::simpleAlta()->toXml(RecordMother::computerSystem());
        $xpath = $this->xpath($xml);

        self::assertSame('525.00', $this->val($xpath, '//sf:CuotaTotal'));
        self::assertSame('3025.00', $this->val($xpath, '//sf:ImporteTotal'));
    }

    public function test_alta_contains_sistema_informatico(): void
    {
        $xml   = RecordMother::simpleAlta()->toXml(RecordMother::computerSystem());
        $xpath = $this->xpath($xml);

        self::assertSame('TestERP', $this->val($xpath, '//sf:SistemaInformatico/sf:NombreSistemaInformatico'));
        self::assertSame('v1.0.0', $this->val($xpath, '//sf:SistemaInformatico/sf:Version'));
        self::assertSame(RecordMother::ISSUER_NIF, $this->val($xpath, '//sf:SistemaInformatico/sf:NIF'));
    }

    public function test_alta_contains_huella_and_tipo_huella(): void
    {
        $record = RecordMother::simpleAlta();
        $xml    = $record->toXml(RecordMother::computerSystem());
        $xpath  = $this->xpath($xml);

        self::assertSame('01', $this->val($xpath, '//sf:TipoHuella'));
        self::assertSame($record->hash(), $this->val($xpath, '//sf:Huella'));
    }

    public function test_first_alta_contains_primer_registro_s(): void
    {
        $xml   = RecordMother::simpleAlta()->toXml(RecordMother::computerSystem());
        $xpath = $this->xpath($xml);

        self::assertSame('S', $this->val($xpath, '//sf:Encadenamiento/sf:PrimerRegistro'));
    }

    public function test_chained_alta_contains_registro_anterior(): void
    {
        [, $second] = RecordMother::batchOfThree();
        $xml   = $second->toXml(RecordMother::computerSystem());
        $xpath = $this->xpath($xml);

        $prevHash = $this->val($xpath, '//sf:RegistroAnterior/sf:Huella');
        self::assertNotEmpty($prevHash);
        self::assertSame(64, strlen($prevHash));
    }

    // -------------------------------------------------------------------------
    // Anulación structure
    // -------------------------------------------------------------------------

    public function test_anulacion_root_element_is_registro_anulacion(): void
    {
        $xml = RecordMother::anulacion()->toXml(RecordMother::computerSystem());
        $dom = new DOMDocument();
        $dom->loadXML($xml);

        self::assertSame('sum:RegistroAnulacion', $dom->documentElement->nodeName);
    }

    public function test_anulacion_contains_id_factura(): void
    {
        $xml   = RecordMother::anulacion()->toXml(RecordMother::computerSystem());
        $xpath = $this->xpath($xml);

        self::assertSame(RecordMother::ISSUER_NIF, $this->val($xpath, '//sf:IDFactura/sf:IDEmisorFactura'));
        self::assertSame('AFAC-001', $this->val($xpath, '//sf:IDFactura/sf:NumSerieFactura'));
    }

    public function test_anulacion_does_not_contain_desglose(): void
    {
        $xml = RecordMother::anulacion()->toXml(RecordMother::computerSystem());

        self::assertStringNotContainsString('<sf:Desglose>', $xml);
        self::assertStringNotContainsString('<sf:ImporteTotal>', $xml);
    }

    // -------------------------------------------------------------------------
    // IGIC / exempt
    // -------------------------------------------------------------------------

    public function test_igic_xml_uses_impuesto_02_and_regime_08(): void
    {
        $xml   = RecordMother::altaWithIGIC()->toXml(RecordMother::computerSystem());
        $xpath = $this->xpath($xml);

        self::assertSame('02', $this->val($xpath, '//sf:DetalleIVA/sf:Impuesto'));
        self::assertSame('08', $this->val($xpath, '//sf:DetalleIVA/sf:ClaveRegimen'));
    }

    public function test_exempt_xml_contains_operacion_exenta_block(): void
    {
        $xml   = RecordMother::altaWithExemption()->toXml(RecordMother::computerSystem());
        $xpath = $this->xpath($xml);

        self::assertSame('E1', $this->val($xpath, '//sf:ExencionIVA/sf:CausaExencion'));
        self::assertSame('500.00', $this->val($xpath, '//sf:ExencionIVA/sf:BaseImponibleExenta'));
    }

    // -------------------------------------------------------------------------
    // Rectificativa
    // -------------------------------------------------------------------------

    public function test_rectificativa_xml_structure(): void
    {
        $xml   = RecordMother::rectificativa()->toXml(RecordMother::computerSystem());
        $xpath = $this->xpath($xml);

        self::assertSame('R1', $this->val($xpath, '//sf:TipoFactura'));
        self::assertSame('S', $this->val($xpath, '//sf:TipoRectificativa'));
        self::assertSame('AFAC-001', $this->val($xpath, '//sf:IDFacturaRectificada/sf:NumSerieFactura'));
        self::assertSame('2500.00', $this->val($xpath, '//sf:ImporteRectificacion/sf:BaseRectificada'));
        self::assertSame('525.00', $this->val($xpath, '//sf:ImporteRectificacion/sf:CuotaRectificada'));
    }

    public function test_rectificativa_diferencias_xml_structure(): void
    {
        $xml   = RecordMother::rectificativaDiferencias()->toXml(RecordMother::computerSystem());
        $xpath = $this->xpath($xml);

        self::assertSame('R1', $this->val($xpath, '//sf:TipoFactura'));
        self::assertSame('I', $this->val($xpath, '//sf:TipoRectificativa'));
    }

    // -------------------------------------------------------------------------
    // Surcharge
    // -------------------------------------------------------------------------

    public function test_surcharge_xml_contains_recargo_fields(): void
    {
        $xml   = RecordMother::altaWithSurcharge()->toXml(RecordMother::computerSystem());
        $xpath = $this->xpath($xml);

        self::assertSame('5.20', $this->val($xpath, '//sf:DetalleIVA/sf:TipoRecargoEquivalencia'));
        self::assertSame('52.00', $this->val($xpath, '//sf:DetalleIVA/sf:CuotaRecargoEquivalencia'));
    }

    // -------------------------------------------------------------------------
    // Simplified / flags
    // -------------------------------------------------------------------------

    public function test_simplified_xml_contains_factura_simplificada_flag(): void
    {
        $xml = RecordMother::altaSimplified()->toXml(RecordMother::computerSystem());

        self::assertStringContainsString('<sf:FacturaSimplificadaArt7273>S</sf:FacturaSimplificadaArt7273>', $xml);
        self::assertStringContainsString('<sf:FacturaSinIdentifDestinatarioArt6_1_d>S</sf:FacturaSinIdentifDestinatarioArt6_1_d>', $xml);
    }
}
