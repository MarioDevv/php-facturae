<?php

declare(strict_types=1);

namespace PhpFacturae\Enums;

enum CorrectionMethod: string
{
    /** Rectificacion integra */
    case FullReplacement = '01';

    /** Rectificacion por diferencias */
    case Differences = '02';

    /** Descuento por volumen de operaciones durante un periodo */
    case VolumeDiscount = '03';

    /** Autorizadas por la Agencia Tributaria */
    case TaxAuthorityAuthorized = '04';
}
