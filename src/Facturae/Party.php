<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Facturae;

use MarioDevv\Rex\Facturae\Entities\Address;

final class Party
{
    private bool    $isLegalEntity;
    private string  $taxNumber;
    private string  $name;
    private ?string $firstSurname = null;
    private ?string $lastSurname = null;
    private ?string $tradeName = null;
    private ?Address $address = null;
    private ?string $email = null;
    private ?string $phone = null;
    private ?string $fax = null;
    private ?string $website = null;
    private ?string $contactPeople = null;
    private ?string $cnoCnae = null;
    private ?string $ineTownCode = null;
    private ?string $book = null;
    private ?string $merchantRegister = null;
    private ?string $sheet = null;
    private ?string $folio = null;
    private ?string $section = null;
    private ?string $volume = null;

    /** @var array<int, array{role: string, code: string, name: ?string}> */
    private array $centres = [];

    private function __construct(bool $isLegalEntity, string $taxNumber, string $name)
    {
        $this->isLegalEntity = $isLegalEntity;
        $this->taxNumber = strtoupper(trim($taxNumber));
        $this->name = $name;
    }

    // ─── Static constructors ─────────────────────────────

    public static function company(string $taxNumber, string $name): self
    {
        return new self(true, $taxNumber, $name);
    }

    public static function person(
        string  $taxNumber,
        string  $name,
        string  $firstSurname,
        ?string $lastSurname = null,
    ): self {
        $party = new self(false, $taxNumber, $name);
        $party->firstSurname = $firstSurname;
        $party->lastSurname = $lastSurname;

        return $party;
    }

    // ─── Fluent setters ──────────────────────────────────

    public function address(
        string $street,
        string $postalCode,
        string $town,
        string $province,
        string $countryCode = 'ESP',
    ): self {
        $this->address = new Address($street, $postalCode, $town, $province, $countryCode);
        return $this;
    }

    public function tradeName(string $tradeName): self       { $this->tradeName = $tradeName; return $this; }
    public function email(string $email): self               { $this->email = $email; return $this; }
    public function phone(string $phone): self               { $this->phone = $phone; return $this; }
    public function fax(string $fax): self                   { $this->fax = $fax; return $this; }
    public function website(string $website): self           { $this->website = $website; return $this; }
    public function contactPeople(string $value): self       { $this->contactPeople = $value; return $this; }
    public function cnoCnae(string $value): self             { $this->cnoCnae = $value; return $this; }
    public function ineTownCode(string $value): self         { $this->ineTownCode = $value; return $this; }

    public function merchantRegister(
        ?string $book = null,
        ?string $register = null,
        ?string $sheet = null,
        ?string $folio = null,
        ?string $section = null,
        ?string $volume = null,
    ): self {
        $this->book = $book;
        $this->merchantRegister = $register;
        $this->sheet = $sheet;
        $this->folio = $folio;
        $this->section = $section;
        $this->volume = $volume;
        return $this;
    }

    public function centre(string $role, string $code, ?string $name = null): self
    {
        $this->centres[] = ['role' => $role, 'code' => $code, 'name' => $name];
        return $this;
    }

    // ─── Getters ─────────────────────────────────────────

    public function isLegalEntity(): bool       { return $this->isLegalEntity; }
    public function taxNumber(): string         { return $this->taxNumber; }
    public function name(): string              { return $this->name; }
    public function firstSurname(): ?string     { return $this->firstSurname; }
    public function lastSurname(): ?string      { return $this->lastSurname; }
    public function getTradeName(): ?string     { return $this->tradeName; }
    public function getAddress(): ?Address      { return $this->address; }
    public function getEmail(): ?string         { return $this->email; }
    public function getPhone(): ?string         { return $this->phone; }
    public function getFax(): ?string           { return $this->fax; }
    public function getWebsite(): ?string       { return $this->website; }
    public function getContactPeople(): ?string { return $this->contactPeople; }
    public function getCnoCnae(): ?string       { return $this->cnoCnae; }
    public function getIneTownCode(): ?string   { return $this->ineTownCode; }
    public function getBook(): ?string          { return $this->book; }
    public function getMerchantRegister(): ?string { return $this->merchantRegister; }
    public function getSheet(): ?string         { return $this->sheet; }
    public function getFolio(): ?string         { return $this->folio; }
    public function getSection(): ?string       { return $this->section; }
    public function getVolume(): ?string        { return $this->volume; }

    /** @return array<int, array{role: string, code: string, name: ?string}> */
    public function getCentres(): array         { return $this->centres; }
}
