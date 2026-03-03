<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Facturae\Enums;

enum Tax: string
{
    case IVA    = '01';
    case IPSI   = '02';
    case IGIC   = '03';
    case IRPF   = '04';
    case Other  = '05';
    case REIVA  = '17';
    case REIGIC = '18';
    case REIPSI = '19';
    case ISS    = '29';
}
