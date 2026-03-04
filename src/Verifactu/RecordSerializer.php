<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Verifactu;

use MarioDevv\Rex\Verifactu\Entities\BreakdownItem;
use MarioDevv\Rex\Verifactu\Entities\Counterparty;

/**
 * Serializa un RegistrationRecord a array / JSON.
 *
 * Útil para depuración, logging, tests de aceptación y almacenamiento
 * del snapshot del registro generado.
 */
final class RecordSerializer
{
    private function __construct(
        private readonly ComputerSystem $system,
    ) {}

    public static function with(ComputerSystem $system): self
    {
        return new self($system);
    }

    /**
     * Serializa el registro a array.
     *
     * @return array<string, mixed>
     */
    public function toArray(RegistrationRecord $record): array
    {
        $data = [
            'tipo_registro'   => $record->getRecordType()->value,
            'emisor'          => [
                'nif'    => $record->getIssuerNif(),
                'nombre' => $this->getIssuerName($record),
            ],
            'factura'         => [
                'serie'           => $record->getSeries(),
                'numero'          => $record->getInvoiceNumber(),
                'numero_completo' => $record->getFullInvoiceNumber(),
                'fecha'           => $record->getIssueDate()->format('d-m-Y'),
                'tipo'            => $record->isAlta() ? $record->getInvoiceType()->value : null,
            ],
            'importes'        => $record->isAlta() ? [
                'cuota_total'    => (float) round($record->getTotalTax(), 2),
                'importe_total'  => (float) round($record->getTotalAmount(), 2),
            ] : null,
            'desgloses'       => $record->isAlta() ? $this->serializeBreakdowns($record->getBreakdowns()) : [],
            'destinatarios'   => $this->serializeCounterparties($record->getCounterparties()),
            'encadenamiento'  => $this->serializeChaining($record),
            'huella'          => [
                'algoritmo' => 'SHA-256',
                'tipo'      => '01',
                'valor'     => $record->hash(),
            ],
            'qr'              => $record->isAlta() ? [
                'url_staging'    => $record->qrUrl(),
                'url_produccion' => $record->production(true)->qrUrl(),
            ] : null,
            'xml'             => $record->toXml($this->system),
        ];

        // Clean nulls from factura block
        $data['factura'] = array_filter($data['factura'], fn($v) => $v !== null);

        return $data;
    }

    /**
     * Serializa el registro a JSON con formato legible.
     */
    public function toJson(RegistrationRecord $record): string
    {
        return json_encode(
            $this->toArray($record),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        );
    }

    // -------------------------------------------------------------------------

    /** @param BreakdownItem[] $breakdowns */
    private function serializeBreakdowns(array $breakdowns): array
    {
        return array_map(fn(BreakdownItem $item) => array_filter([
            'impuesto'            => $item->getTaxType()->name,
            'codigo_impuesto'     => $item->getTaxType()->value,
            'regimen'             => $item->getRegimeType()->value,
            'calificacion'        => $item->getOperationType()->value,
            'exenta'              => $item->isExempt() ?: null,
            'causa_exencion'      => $item->getExemptionCause()?->value,
            'base_imponible'      => (float) ($item->isExempt() ? $item->getExemptBaseAmount() : $item->getBaseAmount()),
            'tipo_impositivo'     => !$item->isExempt() ? (float) $item->getTaxRate() : null,
            'cuota_repercutida'   => !$item->isExempt() ? (float) $item->getTaxAmount() : null,
            'recargo_equivalencia'=> $item->getSurchargeRate() > 0.0 ? [
                'tipo'   => (float) $item->getSurchargeRate(),
                'cuota'  => (float) $item->getSurchargeAmount(),
            ] : null,
        ], fn($v) => $v !== null), $breakdowns);
    }

    /** @param Counterparty[] $counterparties */
    private function serializeCounterparties(array $counterparties): array
    {
        return array_map(fn(Counterparty $cp) => array_filter([
            'nombre'      => $cp->getName(),
            'nif'         => $cp->getNif(),
            'id_otro'     => $cp->getOtherId(),
            'tipo_id'     => $cp->getOtherIdType(),
            'pais'        => $cp->getCountryCode(),
        ], fn($v) => $v !== null), $counterparties);
    }

    private function serializeChaining(RegistrationRecord $record): array
    {
        $prev = $record->getPreviousHash();

        if ($prev === null) {
            return ['primer_registro' => true];
        }

        return [
            'primer_registro'     => false,
            'registro_anterior'   => [
                'nif'    => $prev->getIssuerNif(),
                'numero' => $prev->getInvoiceNumber(),
                'fecha'  => $prev->getFormattedDate(),
                'huella' => $prev->getHash(),
            ],
        ];
    }

    private function getIssuerName(RegistrationRecord $record): string
    {
        return $record->getIssuerName();
    }
}
