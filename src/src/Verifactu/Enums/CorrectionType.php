<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Verifactu\Enums;

/**
 * Tipo de rectificativa (lista L5 — Orden HAC/1177/2024).
 */
enum CorrectionType: string
{
    /** Por sustitución: la factura rectificativa sustituye íntegramente a la original */
    case Substitution = 'S';

    /** Por diferencias: la factura rectificativa contiene solo las diferencias */
    case Differences = 'I';
}
