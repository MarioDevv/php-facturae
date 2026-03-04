<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Facturae\Enums;

enum InvoiceType: string
{
    /** Factura completa */
    case Full = 'FC';

    /** Factura simplificada (ticket) */
    case Simplified = 'FA';

    /** Factura simplificada rectificada y canje */
    case SimplifiedRectified = 'AF';
}
