<?php

declare(strict_types=1);

namespace PhpFacturae\Enums;

/**
 * Unidades de medida del XSD FacturaE 3.2.2.
 *
 * @see Facturae XSD UnitOfMeasureType
 */
enum UnitOfMeasure: string
{
    case Units        = '01';
    case Hours        = '02';
    case Kilograms    = '03';
    case Liters       = '04';
    case Other        = '05';
    case Boxes        = '06';
    case Trays        = '07';
    case Barrels      = '08';
    case Jerricans    = '09';
    case Bags         = '10';
    case Carboys      = '11';
    case Bottles      = '12';
    case Canisters    = '13';
    case Tetrabriks   = '14';
    case Centiliters  = '15';
    case Centimeters  = '16';
    case Bins         = '17';
    case Dozens       = '18';
    case Cases        = '19';
    case Demijohns    = '20';
    case Grams        = '21';
    case Kilometers   = '22';
    case Cans         = '23';
    case Bunches      = '24';
    case Meters       = '25';
    case Millimeters  = '26';
    case SixPacks     = '27';
    case Packages     = '28';
    case Portions     = '29';
    case Rolls        = '30';
    case Envelopes    = '31';
    case Tubs         = '32';
    case CubicMeters  = '33';
    case Seconds      = '34';
    case Watts        = '35';
    case KWh          = '36';
}
