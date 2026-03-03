<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Facturae\Enums;

enum InvoiceType: string
{
    case Full                = 'FC';
    case Simplified          = 'FA';
    case CorrectedFull       = 'FR';
    case CorrectedSimplified = 'FS';
}
