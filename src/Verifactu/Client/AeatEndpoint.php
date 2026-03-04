<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Verifactu\Client;

/**
 * Endpoints del servicio VERI*FACTU de la AEAT.
 *
 * Producción disponible desde abril 2025 (RDL 15/2025).
 * Obligatorio: 01/01/2027 (sociedades), 01/07/2027 (resto).
 */
enum AeatEndpoint: string
{
    /**
     * Entorno de pruebas de la sede electrónica de la AEAT.
     * No requiere NIF real. Los CSV generados no tienen validez legal.
     */
    case Staging = 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SuministroFacturasEmitidas';

    /**
     * Entorno de producción.
     * Requiere certificado electrónico válido y NIF real del obligado.
     */
    case Production = 'https://www1.aeat.es/wlpl/TIKE-CONT/ws/SuministroFacturasEmitidas';

    /**
     * URL del portal de consulta de registros (sede electrónica).
     */
    public function queryUrl(): string
    {
        return match($this) {
            self::Staging    => 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/ConsultaFacturasEmitidas',
            self::Production => 'https://www1.aeat.es/wlpl/TIKE-CONT/ws/ConsultaFacturasEmitidas',
        };
    }
}
