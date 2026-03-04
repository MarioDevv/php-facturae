<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Verifactu\Entities;

/**
 * Destinatario / contraparte de la factura.
 */
final class Counterparty
{
    /**
     * @param string $nif      NIF nacional (o null si es extranjero)
     * @param string $name     Nombre o razón social
     * @param string|null $otherId   Identificador de otro país (para no residentes)
     * @param string|null $otherIdType  Tipo ID extranjero (02=NIF IVA, 03=Pasaporte, etc.)
     * @param string|null $countryCode  Código ISO 3166-1 alpha-2 del país (si es extranjero)
     */
    public function __construct(
        private readonly ?string $nif,
        private readonly string $name,
        private readonly ?string $otherId = null,
        private readonly ?string $otherIdType = null,
        private readonly ?string $countryCode = null,
    ) {}

    /**
     * Crea una contraparte con NIF nacional.
     */
    public static function withNif(string $nif, string $name): self
    {
        return new self(nif: $nif, name: $name);
    }

    /**
     * Crea una contraparte extranjera con identificador de otro país.
     */
    public static function foreign(string $name, string $otherId, string $countryCode, string $otherIdType = '04'): self
    {
        return new self(
            nif: null,
            name: $name,
            otherId: $otherId,
            otherIdType: $otherIdType,
            countryCode: $countryCode,
        );
    }

    public function getNif(): ?string { return $this->nif; }
    public function getName(): string { return $this->name; }
    public function getOtherId(): ?string { return $this->otherId; }
    public function getOtherIdType(): ?string { return $this->otherIdType; }
    public function getCountryCode(): ?string { return $this->countryCode; }
    public function isNational(): bool { return $this->nif !== null; }
}
