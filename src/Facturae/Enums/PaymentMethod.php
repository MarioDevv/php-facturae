<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Facturae\Enums;

enum PaymentMethod: string
{
    case Cash           = '01';
    case DirectDebit    = '02';
    case Transfer       = '04';
    case PromissoryNote = '09';
    case Cheque         = '11';
    case Card           = '19';
}
