<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Verifactu;

use MarioDevv\Rex\Verifactu\Exceptions\InvalidRecordException;

/**
 * Identificación del Sistema Informático de Facturación (SIF).
 *
 * Contiene los datos que la AEAT exige para identificar el software
 * que genera los registros Verifactu.
 */
final class ComputerSystem
{
    private string $producerNif = '';
    private string $producerName = '';
    private string $installationId = '00';
    private bool $onlyVerifactu = true;
    private bool $multipleObligatedParties = false;

    private function __construct(
        private readonly string $name,
        private readonly string $version,
    ) {}

    /**
     * Crea un nuevo sistema informático.
     *
     * @param string $name    Nombre del sistema / software
     * @param string $version Versión del sistema
     */
    public static function create(string $name, string $version): self
    {
        return new self($name, $version);
    }

    /**
     * Datos del productor del sistema informático (puede ser distinto del emisor).
     *
     * @param string $nif  NIF del productor / proveedor del software
     * @param string $name Nombre o razón social del productor
     */
    public function producer(string $nif, string $name): self
    {
        $clone = clone $this;
        $clone->producerNif = $nif;
        $clone->producerName = $name;
        return $clone;
    }

    /**
     * Número de instalación del sistema.
     */
    public function installationId(string $id): self
    {
        $clone = clone $this;
        $clone->installationId = $id;
        return $clone;
    }

    /**
     * Indica si el sistema se usa exclusivamente para Verifactu.
     */
    public function onlyVerifactu(bool $value = true): self
    {
        $clone = clone $this;
        $clone->onlyVerifactu = $value;
        return $clone;
    }

    /**
     * Indica si el sistema puede usarse por varios obligados tributarios.
     */
    public function multipleObligatedParties(bool $value = true): self
    {
        $clone = clone $this;
        $clone->multipleObligatedParties = $value;
        return $clone;
    }

    public function validate(): void
    {
        if ($this->producerNif === '') {
            throw InvalidRecordException::missingField('ComputerSystem::producerNif (llama a ->producer())');
        }
        if ($this->producerName === '') {
            throw InvalidRecordException::missingField('ComputerSystem::producerName (llama a ->producer())');
        }
    }

    public function getName(): string { return $this->name; }
    public function getVersion(): string { return $this->version; }
    public function getProducerNif(): string { return $this->producerNif; }
    public function getProducerName(): string { return $this->producerName; }
    public function getInstallationId(): string { return $this->installationId; }
    public function isOnlyVerifactu(): bool { return $this->onlyVerifactu; }
    public function isMultipleObligatedParties(): bool { return $this->multipleObligatedParties; }

    private function boolToSN(bool $value): string
    {
        return $value ? 'S' : 'N';
    }

    /**
     * Devuelve los campos para el bloque XML SistemaInformatico.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'NombreRazon'                    => $this->producerName,
            'NIF'                            => $this->producerNif,
            'NombreSistemaInformatico'       => $this->name,
            'IdSistemaInformatico'           => substr(hash('sha256', $this->producerNif . $this->name), 0, 2),
            'Version'                        => $this->version,
            'NumeroInstalacion'              => $this->installationId,
            'TipoUsoPosibleSoloVerifactu'    => $this->boolToSN($this->onlyVerifactu),
            'TipoUsoPosibleMultiOT'          => $this->boolToSN($this->multipleObligatedParties),
            'IndicadorMultiplesOT'           => $this->boolToSN($this->multipleObligatedParties),
        ];
    }
}
