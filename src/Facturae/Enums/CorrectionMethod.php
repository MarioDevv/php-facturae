<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Facturae\Enums;

enum CorrectionMethod: string
{
    case FullReplacement       = '01';
    case DifferencesOnly       = '02';
    case BulkDiscount          = '03';
    case AuthorizedByTaxAgency = '04';
}
