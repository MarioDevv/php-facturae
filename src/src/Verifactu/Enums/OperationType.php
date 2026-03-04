<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Verifactu\Enums;

/**
 * Calificación de la operación a efectos del IVA (lista L9 — Orden HAC/1177/2024).
 */
enum OperationType: string
{
    /** Sujeta y no exenta - Sin inversión del sujeto pasivo */
    case SubjectNotExempt = 'S1';

    /** Sujeta y no exenta - Con inversión del sujeto pasivo */
    case SubjectNotExemptReverseCharge = 'S2';

    /** No sujeta por el artículo 7, 14 u otros */
    case NotSubjectArt7_14 = 'N1';

    /** No sujeta por reglas de localización */
    case NotSubjectLocation = 'N2';

    /** Exenta */
    case Exempt = 'E';

    /** Sujeta y no exenta - No deducible */
    case SubjectNotExemptNotDeductible = 'O';

    /** No sujeta con derecho a deducción – arts. 7.8.º y 94.Uno 2.º LIVA */
    case NotSubjectWithDeduction = 'AE';
}
