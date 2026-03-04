<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Verifactu\Xml;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use MarioDevv\Rex\Verifactu\ComputerSystem;
use MarioDevv\Rex\Verifactu\Entities\BreakdownItem;
use MarioDevv\Rex\Verifactu\Entities\CorrectionAmount;
use MarioDevv\Rex\Verifactu\Entities\Counterparty;
use MarioDevv\Rex\Verifactu\Entities\InvoiceReference;
use MarioDevv\Rex\Verifactu\Entities\PreviousHash;
use MarioDevv\Rex\Verifactu\Enums\CorrectionType;
use MarioDevv\Rex\Verifactu\Enums\InvoiceType;
use MarioDevv\Rex\Verifactu\Enums\OperationType;
use MarioDevv\Rex\Verifactu\Enums\RecordType;
use MarioDevv\Rex\Verifactu\Enums\RegimeType;

/**
 * Genera el payload XML conforme al esquema de la AEAT (Orden HAC/1177/2024).
 *
 * Namespace: https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/
 *            es/aeat/tikeV/cont/ws/SuministroInformacion.xsd
 */
final class XmlBuilder
{
    private const NS_SII  = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV/cont/ws/SuministroInformacion.xsd';
    private const NS_SII2 = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV/cont/ws/SuministroLR.xsd';

    private DOMDocument $dom;
    private DOMElement $root;

    private function __construct()
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;
    }

    // -------------------------------------------------------------------------
    // Public factory
    // -------------------------------------------------------------------------

    /**
     * Construye el XML completo para un registro de alta.
     */
    public static function forAlta(
        string $issuerNif,
        string $issuerName,
        string $invoiceNumber,
        string $series,
        DateTimeImmutable $issueDate,
        DateTimeImmutable $generatedAt,
        string $hash,
        InvoiceType $invoiceType,
        string $description,
        float $totalTax,
        float $totalAmount,
        ComputerSystem $system,
        array $breakdowns,
        ?PreviousHash $previousHash,
        ?array $counterparties,
        ?CorrectionType $correctionType,
        ?array $rectifiedInvoices,
        ?array $substitutedInvoices,
        ?CorrectionAmount $correctionAmount,
        ?DateTimeImmutable $operationDate,
        bool $simplifiedArt7273,
        bool $noRecipientId,
        bool $macrodato,
    ): string {
        $builder = new self();
        return $builder->buildAlta(
            $issuerNif, $issuerName, $invoiceNumber, $series, $issueDate,
            $generatedAt, $hash, $invoiceType, $description, $totalTax, $totalAmount,
            $system, $breakdowns, $previousHash, $counterparties, $correctionType,
            $rectifiedInvoices, $substitutedInvoices, $correctionAmount, $operationDate,
            $simplifiedArt7273, $noRecipientId, $macrodato,
        );
    }

    /**
     * Construye el XML completo para un registro de anulación.
     */
    public static function forAnulacion(
        string $issuerNif,
        string $issuerName,
        string $invoiceNumber,
        string $series,
        DateTimeImmutable $issueDate,
        DateTimeImmutable $generatedAt,
        string $hash,
        ComputerSystem $system,
        ?PreviousHash $previousHash,
    ): string {
        $builder = new self();
        return $builder->buildAnulacion(
            $issuerNif, $issuerName, $invoiceNumber, $series,
            $issueDate, $generatedAt, $hash, $system, $previousHash,
        );
    }

    // -------------------------------------------------------------------------
    // Alta builder
    // -------------------------------------------------------------------------

    private function buildAlta(
        string $issuerNif,
        string $issuerName,
        string $invoiceNumber,
        string $series,
        DateTimeImmutable $issueDate,
        DateTimeImmutable $generatedAt,
        string $hash,
        InvoiceType $invoiceType,
        string $description,
        float $totalTax,
        float $totalAmount,
        ComputerSystem $system,
        array $breakdowns,
        ?PreviousHash $previousHash,
        ?array $counterparties,
        ?CorrectionType $correctionType,
        ?array $rectifiedInvoices,
        ?array $substitutedInvoices,
        ?CorrectionAmount $correctionAmount,
        ?DateTimeImmutable $operationDate,
        bool $simplifiedArt7273,
        bool $noRecipientId,
        bool $macrodato,
    ): string {
        $root = $this->dom->createElementNS(self::NS_SII2, 'sum:RegFactuSistemaFacturacion');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sf', self::NS_SII);
        $this->dom->appendChild($root);

        // IDFactura
        $idFactura = $this->elem('sf:IDFactura');
        $idFactura->appendChild($this->text('sf:IDEmisorFactura', $issuerNif));
        $numSerie = $series !== '' ? $series . $invoiceNumber : $invoiceNumber;
        $idFactura->appendChild($this->text('sf:NumSerieFactura', $numSerie));
        $idFactura->appendChild($this->text('sf:FechaExpedicionFactura', $issueDate->format('d-m-Y')));
        $root->appendChild($idFactura);

        // NombreRazonEmisor
        $root->appendChild($this->text('sf:NombreRazonEmisor', $issuerName));

        // TipoFactura
        $root->appendChild($this->text('sf:TipoFactura', $invoiceType->value));

        // TipoRectificativa (solo si es rectificativa)
        if ($correctionType !== null) {
            $root->appendChild($this->text('sf:TipoRectificativa', $correctionType->value));
        }

        // FacturasRectificadas
        if (!empty($rectifiedInvoices)) {
            $facturasRect = $this->elem('sf:FacturasRectificadas');
            foreach ($rectifiedInvoices as $ref) {
                /** @var InvoiceReference $ref */
                $idRefRect = $this->elem('sf:IDFacturaRectificada');
                $idRefRect->appendChild($this->text('sf:IDEmisorFactura', $ref->getIssuerNif()));
                $idRefRect->appendChild($this->text('sf:NumSerieFactura', $ref->getFullNumber()));
                $idRefRect->appendChild($this->text('sf:FechaExpedicionFactura', $ref->getFormattedDate()));
                $facturasRect->appendChild($idRefRect);
            }
            $root->appendChild($facturasRect);
        }

        // FacturasSustituidas
        if (!empty($substitutedInvoices)) {
            $facturasSust = $this->elem('sf:FacturasSustituidas');
            foreach ($substitutedInvoices as $ref) {
                /** @var InvoiceReference $ref */
                $idRefSust = $this->elem('sf:IDFacturaSustituida');
                $idRefSust->appendChild($this->text('sf:IDEmisorFactura', $ref->getIssuerNif()));
                $idRefSust->appendChild($this->text('sf:NumSerieFactura', $ref->getFullNumber()));
                $idRefSust->appendChild($this->text('sf:FechaExpedicionFactura', $ref->getFormattedDate()));
                $facturasSust->appendChild($idRefSust);
            }
            $root->appendChild($facturasSust);
        }

        // ImporteRectificacion
        if ($correctionAmount !== null) {
            $impRect = $this->elem('sf:ImporteRectificacion');
            $impRect->appendChild($this->text('sf:BaseRectificada', $this->fmt($correctionAmount->getCorrectedBase())));
            $impRect->appendChild($this->text('sf:CuotaRectificada', $this->fmt($correctionAmount->getCorrectedTax())));
            if ($correctionAmount->getCorrectedSurcharge() != 0.0) {
                $impRect->appendChild($this->text('sf:CuotaRecargoRectificado', $this->fmt($correctionAmount->getCorrectedSurcharge())));
            }
            $root->appendChild($impRect);
        }

        // FechaOperacion
        if ($operationDate !== null) {
            $root->appendChild($this->text('sf:FechaOperacion', $operationDate->format('d-m-Y')));
        }

        // DescripcionOperacion
        $root->appendChild($this->text('sf:DescripcionOperacion', $description));

        // Flags
        if ($simplifiedArt7273) {
            $root->appendChild($this->text('sf:FacturaSimplificadaArt7273', 'S'));
        }
        if ($noRecipientId) {
            $root->appendChild($this->text('sf:FacturaSinIdentifDestinatarioArt6_1_d', 'S'));
        }
        if ($macrodato) {
            $root->appendChild($this->text('sf:Macrodato', 'S'));
        }

        // Destinatarios
        if (!empty($counterparties)) {
            $destNode = $this->elem('sf:Destinatarios');
            foreach ($counterparties as $cp) {
                /** @var Counterparty $cp */
                $idDest = $this->elem('sf:IDDestinatario');
                $idDest->appendChild($this->text('sf:NombreRazon', $cp->getName()));
                if ($cp->isNational()) {
                    $idDest->appendChild($this->text('sf:NIF', $cp->getNif()));
                } else {
                    $idOtro = $this->elem('sf:IDOtro');
                    if ($cp->getCountryCode() !== null) {
                        $idOtro->appendChild($this->text('sf:CodigoPais', $cp->getCountryCode()));
                    }
                    $idOtro->appendChild($this->text('sf:IDType', $cp->getOtherIdType() ?? '04'));
                    $idOtro->appendChild($this->text('sf:ID', $cp->getOtherId() ?? ''));
                    $idDest->appendChild($idOtro);
                }
                $destNode->appendChild($idDest);
            }
            $root->appendChild($destNode);
        }

        // Desglose
        if (!empty($breakdowns)) {
            $desglose = $this->elem('sf:Desglose');
            foreach ($breakdowns as $item) {
                /** @var BreakdownItem $item */
                $detalle = $this->elem('sf:DetalleIVA');
                $detalle->appendChild($this->text('sf:Impuesto', $item->getTaxType()->value));
                $detalle->appendChild($this->text('sf:ClaveRegimen', $item->getRegimeType()->value));
                $detalle->appendChild($this->text('sf:CalificacionOperacion', $item->getOperationType()->value));

                if ($item->isExempt() && $item->getExemptionCause() !== null) {
                    $opExenta = $this->elem('sf:OperacionExenta');
                    $exItem = $this->elem('sf:ExencionIVA');
                    $exItem->appendChild($this->text('sf:CausaExencion', $item->getExemptionCause()->value));
                    $exItem->appendChild($this->text('sf:BaseImponibleExenta', $this->fmt($item->getExemptBaseAmount())));
                    $opExenta->appendChild($exItem);
                    $detalle->appendChild($opExenta);
                } else {
                    $detalle->appendChild($this->text('sf:TipoImpositivo', $this->fmt($item->getTaxRate())));
                    $detalle->appendChild($this->text('sf:BaseImponible', $this->fmt($item->getBaseAmount())));
                    $detalle->appendChild($this->text('sf:CuotaRepercutida', $this->fmt($item->getTaxAmount())));
                    if ($item->getSurchargeRate() != 0.0) {
                        $detalle->appendChild($this->text('sf:TipoRecargoEquivalencia', $this->fmt($item->getSurchargeRate())));
                        $detalle->appendChild($this->text('sf:CuotaRecargoEquivalencia', $this->fmt($item->getSurchargeAmount())));
                    }
                }
                $desglose->appendChild($detalle);
            }
            $root->appendChild($desglose);
        }

        // CuotaTotal / ImporteTotal
        $root->appendChild($this->text('sf:CuotaTotal', $this->fmt($totalTax)));
        $root->appendChild($this->text('sf:ImporteTotal', $this->fmt($totalAmount)));

        // Encadenamiento
        $root->appendChild($this->buildEncadenamiento($issuerNif, $invoiceNumber, $series, $issueDate, $previousHash));

        // SistemaInformatico
        $root->appendChild($this->buildSistemaInformatico($system));

        // FechaHoraHusoGenRegistro
        $root->appendChild($this->text('sf:FechaHoraHusoGenRegistro', $generatedAt->format('Y-m-d\TH:i:sP')));

        // TipoHuella + Huella
        $root->appendChild($this->text('sf:TipoHuella', '01'));
        $root->appendChild($this->text('sf:Huella', $hash));

        return $this->dom->saveXML() ?: '';
    }

    // -------------------------------------------------------------------------
    // Anulación builder
    // -------------------------------------------------------------------------

    private function buildAnulacion(
        string $issuerNif,
        string $issuerName,
        string $invoiceNumber,
        string $series,
        DateTimeImmutable $issueDate,
        DateTimeImmutable $generatedAt,
        string $hash,
        ComputerSystem $system,
        ?PreviousHash $previousHash,
    ): string {
        $root = $this->dom->createElementNS(self::NS_SII2, 'sum:RegistroAnulacion');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sf', self::NS_SII);
        $this->dom->appendChild($root);

        // IDFactura
        $idFactura = $this->elem('sf:IDFactura');
        $idFactura->appendChild($this->text('sf:IDEmisorFactura', $issuerNif));
        $numSerie = $series !== '' ? $series . $invoiceNumber : $invoiceNumber;
        $idFactura->appendChild($this->text('sf:NumSerieFactura', $numSerie));
        $idFactura->appendChild($this->text('sf:FechaExpedicionFactura', $issueDate->format('d-m-Y')));
        $root->appendChild($idFactura);

        $root->appendChild($this->text('sf:NombreRazonEmisor', $issuerName));

        // Encadenamiento
        $root->appendChild($this->buildEncadenamiento($issuerNif, $invoiceNumber, $series, $issueDate, $previousHash));

        // SistemaInformatico
        $root->appendChild($this->buildSistemaInformatico($system));

        // FechaHoraHusoGenRegistro
        $root->appendChild($this->text('sf:FechaHoraHusoGenRegistro', $generatedAt->format('Y-m-d\TH:i:sP')));

        // TipoHuella + Huella
        $root->appendChild($this->text('sf:TipoHuella', '01'));
        $root->appendChild($this->text('sf:Huella', $hash));

        return $this->dom->saveXML() ?: '';
    }

    // -------------------------------------------------------------------------
    // Shared blocks
    // -------------------------------------------------------------------------

    private function buildEncadenamiento(
        string $issuerNif,
        string $invoiceNumber,
        string $series,
        DateTimeImmutable $issueDate,
        ?PreviousHash $previousHash,
    ): DOMElement {
        $encadenamientoNode = $this->elem('sf:Encadenamiento');

        if ($previousHash === null) {
            $encadenamientoNode->appendChild($this->text('sf:PrimerRegistro', 'S'));
        } else {
            $registroAnterior = $this->elem('sf:RegistroAnterior');
            $registroAnterior->appendChild($this->text('sf:IDEmisorFactura', $previousHash->getIssuerNif()));
            $registroAnterior->appendChild($this->text('sf:NumSerieFactura', $previousHash->getInvoiceNumber()));
            $registroAnterior->appendChild($this->text('sf:FechaExpedicionFactura', $previousHash->getFormattedDate()));
            $registroAnterior->appendChild($this->text('sf:Huella', $previousHash->getHash()));
            $encadenamientoNode->appendChild($registroAnterior);
        }

        return $encadenamientoNode;
    }

    private function buildSistemaInformatico(ComputerSystem $system): DOMElement
    {
        $si = $this->elem('sf:SistemaInformatico');
        $data = $system->toArray();
        foreach ($data as $tag => $value) {
            $si->appendChild($this->text("sf:{$tag}", $value));
        }
        return $si;
    }

    // -------------------------------------------------------------------------
    // DOM helpers
    // -------------------------------------------------------------------------

    private function elem(string $tag): DOMElement
    {
        return $this->dom->createElement($tag);
    }

    private function text(string $tag, string $value): DOMElement
    {
        $el = $this->dom->createElement($tag);
        $el->appendChild($this->dom->createTextNode($value));
        return $el;
    }

    private function fmt(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
