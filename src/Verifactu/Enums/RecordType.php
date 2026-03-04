<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Verifactu\Enums;

/**
 * Tipo de operación del registro (alta o anulación).
 *
 * @internal
 */
enum RecordType: string
{
    case Alta = 'Alta';
    case Anulacion = 'Anulacion';
}
