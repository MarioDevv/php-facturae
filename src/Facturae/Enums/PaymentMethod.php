<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Facturae\Enums;

enum PaymentMethod: string
{
    case Cash           = '01';
    case DirectDebit    = '02';
    case Receipt        = '03';
    case Transfer       = '04';
    case PromissoryNote = '05';
    case Check          = '07';
    case Card           = '10';
    case Compensation   = '11';
}
