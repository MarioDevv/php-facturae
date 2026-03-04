<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Verifactu\Enums;

/**
 * Tipo de impuesto (lista L26 — Orden HAC/1177/2024).
 */
enum TaxType: string
{
    /** Impuesto sobre el Valor Añadido */
    case IVA = '01';

    /** Impuesto General Indirecto Canario */
    case IGIC = '02';

    /** Impuesto sobre la Producción, los Servicios y la Importación */
    case IPSI = '03';
}
