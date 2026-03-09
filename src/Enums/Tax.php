<?php

declare(strict_types=1);

namespace PhpFacturae\Enums;

/**
 * Tipos de impuestos del XSD FacturaE 3.2.2.
 *
 * @see Facturae XSD TaxTypeCodeType
 */
enum Tax: string
{
    case IVA      = '01';
    case IPSI     = '02';
    case IGIC     = '03';
    case IRPF     = '04';
    case Other    = '05';
    case ITPAJD   = '06';
    case IE       = '07';
    case RA       = '08';
    case IGTECM   = '09';
    case IECDPCAC = '10';
    case IIIMAB   = '11';
    case ICIO     = '12';
    case IMVDN    = '13';
    case IMSN     = '14';
    case IMGSN    = '15';
    case IMPN     = '16';
    case REIVA    = '17';
    case REIGIC   = '18';
    case REIPSI   = '19';
    case IPS      = '20';
    case RLEA     = '21';
    case IVPEE    = '22';
    case IPCNG    = '23';
    case IACNG    = '24';
    case IDEC     = '25';
    case ILTCAC   = '26';
    case IGFEI    = '27';
    case IRNR     = '28';
    case ISS      = '29';

    /**
     * Indica si este impuesto es retenido por defecto.
     * Se puede sobreescribir con isWithholding en TaxBreakdown.
     */
    public function isWithheldByDefault(): bool
    {
        return match ($this) {
            self::IRPF, self::IRNR => true,
            default => false,
        };
    }
}
