<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Verifactu\Enums;

/**
 * Clave de régimen del IVA o una operación con trascendencia tributaria
 * (lista L8 — Orden HAC/1177/2024).
 */
enum RegimeType: string
{
    /** Operación de régimen general y cualquier otro régimen que no proceda consignar en los siguientes */
    case General = '01';

    /** Exportación */
    case Export = '02';

    /** Operaciones a las que se aplique el régimen especial de bienes usados, objetos de arte, antigüedades y objetos de colección */
    case UsedGoods = '03';

    /** Régimen especial del oro de inversión */
    case InvestmentGold = '04';

    /** Régimen especial de las agencias de viajes */
    case TravelAgencies = '05';

    /** Régimen especial grupo de entidades en IVA (Nivel Avanzado) */
    case GroupedEntities = '06';

    /** Régimen especial del criterio de caja */
    case CashCriteria = '07';

    /** Operaciones sujetas al IPSI / IGIC */
    case IPSI_IGIC = '08';

    /** Adquisiciones intracomunitarias de bienes y prestaciones de servicios */
    case IntraCommunityAcquisition = '09';

    /** Cobros por cuenta de terceros de honorarios profesionales o de derechos derivados de la propiedad industrial */
    case ThirdPartyFees = '10';

    /** Operaciones de arrendamiento de local de negocio */
    case BusinessPremisesRental = '11';

    /** Operación calificada como de prestación de servicios de inversión sujeto pasivo */
    case InvestmentServiceReverseCharge = '12';

    /** Actividades accesorias */
    case AncillaryActivities = '13';

    /** Factura con IVA pendiente de devengo - operaciones de tracto sucesivo */
    case DeferredVAT = '14';

    /** Operaciones de seguros */
    case Insurance = '15';

    /** Arrendamiento de local de negocio con opción de compra */
    case BusinessPremisesRentalWithOption = '16';

    /** Operación acogida a alguno de los regímenes previstos en el Cap. XI del Tít. IX (OSS e IOSS) */
    case OSS_IOSS = '17';

    /** Régimen especial para agricultores y ganaderos */
    case AgricultureFlatRate = '18';
}
