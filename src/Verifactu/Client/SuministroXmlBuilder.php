<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Verifactu\Client;

use DateTimeImmutable;
use DOMDocument;
use MarioDevv\Rex\Verifactu\ComputerSystem;
use MarioDevv\Rex\Verifactu\Enums\RecordType;
use MarioDevv\Rex\Verifactu\RegistrationRecord;

/**
 * Construye el documento XML SuministroLR completo para enviar a la AEAT.
 *
 * El SuministroLR envuelve uno o varios registros (alta o anulación)
 * junto con los datos del obligado tributario y el período de liquidación.
 *
 * @internal
 */
final class SuministroXmlBuilder
{
    private const NS_SLR = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV/cont/ws/SuministroLR.xsd';
    private const NS_SII = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV/cont/ws/SuministroInformacion.xsd';

    /**
     * Genera el XML SuministroLR para uno o varios registros.
     *
     * @param RegistrationRecord[] $records
     */
    public static function build(
        array $records,
        ComputerSystem $system,
        string $obligadoNif,
        string $obligadoNombre,
        string $ejercicio,
        string $periodo,
    ): string {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS(self::NS_SLR, 'sum:SuministroLR');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sf', self::NS_SII);
        $dom->appendChild($root);

        // Cabecera
        $cabecera = $dom->createElement('sum:Cabecera');
        $root->appendChild($cabecera);

        // IDVersionSii
        $cabecera->appendChild(self::text($dom, 'sum:IDVersionSii', '1.0'));

        // ObligadoTributario
        $obligado = $dom->createElement('sum:ObligadoTributario');
        $obligado->appendChild(self::text($dom, 'sum:NIF', $obligadoNif));
        $obligado->appendChild(self::text($dom, 'sum:NombreRazon', $obligadoNombre));
        $cabecera->appendChild($obligado);

        // TipoComunicacion (A0 = alta inicial)
        $cabecera->appendChild(self::text($dom, 'sum:TipoComunicacion', 'A0'));

        // Registros
        foreach ($records as $record) {
            $recordXmlStr = $record->toXml($system);
            $recordDom    = new DOMDocument();
            $recordDom->loadXML($recordXmlStr);

            $imported = $dom->importNode($recordDom->documentElement, true);

            if ($record->getRecordType() === RecordType::Alta) {
                $wrapper = $dom->createElement('sum:RegistroFactura');
                $wrapper->appendChild($imported);
            } else {
                $wrapper = $dom->createElement('sum:BajaFactura');
                $wrapper->appendChild($imported);
            }

            $root->appendChild($wrapper);
        }

        return $dom->saveXML() ?: '';
    }

    private static function text(DOMDocument $dom, string $tag, string $value): \DOMElement
    {
        $el = $dom->createElement($tag);
        $el->appendChild($dom->createTextNode($value));
        return $el;
    }
}
