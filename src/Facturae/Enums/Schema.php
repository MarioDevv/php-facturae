<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Facturae\Enums;

enum Schema: string
{
    case V3_2   = '3.2';
    case V3_2_1 = '3.2.1';
    case V3_2_2 = '3.2.2';

    /** XML namespace URI for this schema version. */
    public function xmlNamespace(): string
    {
        return match ($this) {
            self::V3_2   => 'http://www.facturae.es/Facturae/2009/v3.2/Facturae',
            self::V3_2_1 => 'http://www.facturae.es/Facturae/2014/v3.2.1/Facturae',
            self::V3_2_2 => 'http://www.facturae.es/Facturae/2015/v3.2.2/Facturae',
        };
    }

    public function schemaUrl(): string
    {
        return match ($this) {
            self::V3_2   => 'http://www.facturae.gob.es/formato/Versiones/Facturaev3_2.xml',
            self::V3_2_1 => 'http://www.facturae.gob.es/formato/Versiones/Facturaev3_2_1.xml',
            self::V3_2_2 => 'http://www.facturae.gob.es/formato/Versiones/Facturaev3_2_2.xml',
        };
    }
}
