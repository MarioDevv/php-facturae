<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Verifactu\Enums;

/**
 * Causa de exención (lista L10 — Orden HAC/1177/2024).
 */
enum ExemptionCause: string
{
    /** Exenta por el artículo 20 LIVA */
    case Art20 = 'E1';

    /** Exenta por los artículos 21, 22, 23, 24 y 25 LIVA (exportaciones y operaciones asimiladas) */
    case Art21_25 = 'E2';

    /** Exenta por el artículo 26 LIVA */
    case Art26 = 'E3';

    /** Exenta por el artículo 27 LIVA */
    case Art27 = 'E4';

    /** Exenta por los artículos 45 al 65 LIVA (operaciones exentas por ley) */
    case Art45_65 = 'E5';

    /** Exenta por otra causa */
    case Other = 'E6';

    /** Exenta por el artículo 7 LIVA (operaciones no sujetas por su naturaleza) */
    case Art7 = 'E7';
}
