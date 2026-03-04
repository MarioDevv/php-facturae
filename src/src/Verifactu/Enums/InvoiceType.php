<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Verifactu\Enums;

/**
 * Tipos de factura (lista L1 — Orden HAC/1177/2024).
 */
enum InvoiceType: string
{
    /** Factura (art. 6,7.2 y 7.3 RD 1619/2012) */
    case FullInvoice = 'F1';

    /** Factura simplificada y facturas sin identificación del destinatario art.6.1.d RD 1619/2012 */
    case SimplifiedInvoice = 'F2';

    /** Factura emitida en sustitución de facturas simplificadas facturadas y declaradas */
    case SimplifiedSubstitution = 'F3';

    /** Factura rectificativa (Art. 80.1, 2 y 6 y art. 6.3 del RD 1619/2012) */
    case CorrectionArt80_1_2_6 = 'R1';

    /** Factura rectificativa (Art. 80.3) */
    case CorrectionArt80_3 = 'R2';

    /** Factura rectificativa (Art. 80.4) */
    case CorrectionArt80_4 = 'R3';

    /** Factura rectificativa (Otras causas) */
    case CorrectionOther = 'R4';

    /** Factura rectificativa simplificada */
    case SimplifiedCorrection = 'R5';

    /** Asiento resumen de facturas */
    case BatchSummary = 'F4';

    /** Importaciones (DUA) */
    case Import = 'F5';

    /** Otros justificantes contables */
    case OtherJustification = 'F6';

    public function isCorrection(): bool
    {
        return in_array($this, [
            self::CorrectionArt80_1_2_6,
            self::CorrectionArt80_3,
            self::CorrectionArt80_4,
            self::CorrectionOther,
            self::SimplifiedCorrection,
        ]);
    }
}
