<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Facturae\Enums;

/**
 * Codigo de fiscalidad especial para lineas de producto.
 *
 * @see Facturae XSD SpecialTaxableEventCodeType
 */
enum SpecialTaxableEvent: string
{
    /** Operacion sujeta y no exenta */
    case Taxable = '01';

    /** Operacion exenta */
    case Exempt = '02';

    /** Operacion no sujeta */
    case NotSubject = '03';
}
