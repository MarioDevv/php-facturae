<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Tests\Verifactu;

use MarioDevv\Rex\Tests\Verifactu\Mother\RecordMother;
use MarioDevv\Rex\Verifactu\ComputerSystem;
use MarioDevv\Rex\Verifactu\Exceptions\InvalidRecordException;
use PHPUnit\Framework\TestCase;

final class ComputerSystemTest extends TestCase
{
    public function test_create_returns_computer_system(): void
    {
        $system = ComputerSystem::create('TestERP', 'v1.0.0');

        self::assertInstanceOf(ComputerSystem::class, $system);
    }

    public function test_fluent_api_is_immutable(): void
    {
        $base     = ComputerSystem::create('ERP', 'v1.0');
        $withProd = $base->producer('B76123456', 'Atlantic Systems S.L.');

        self::assertNotSame($base, $withProd);
    }

    public function test_to_array_contains_expected_keys(): void
    {
        $system = RecordMother::computerSystem();
        $data   = $system->toArray();

        self::assertArrayHasKey('NombreRazon', $data);
        self::assertArrayHasKey('NIF', $data);
        self::assertArrayHasKey('NombreSistemaInformatico', $data);
        self::assertArrayHasKey('Version', $data);
        self::assertArrayHasKey('NumeroInstalacion', $data);
        self::assertArrayHasKey('TipoUsoPosibleSoloVerifactu', $data);
        self::assertArrayHasKey('TipoUsoPosibleMultiOT', $data);
    }

    public function test_to_array_has_correct_values(): void
    {
        $system = RecordMother::computerSystem();
        $data   = $system->toArray();

        self::assertSame(RecordMother::ISSUER_NIF, $data['NIF']);
        self::assertSame('TestERP', $data['NombreSistemaInformatico']);
        self::assertSame('v1.0.0', $data['Version']);
        self::assertSame('01', $data['NumeroInstalacion']);
    }

    public function test_only_verifactu_defaults_to_true(): void
    {
        $system = RecordMother::computerSystem();
        $data   = $system->toArray();

        self::assertSame('S', $data['TipoUsoPosibleSoloVerifactu']);
    }

    public function test_multiple_obligated_parties_defaults_to_false(): void
    {
        $system = RecordMother::computerSystem();
        $data   = $system->toArray();

        self::assertSame('N', $data['TipoUsoPosibleMultiOT']);
    }

    public function test_validate_throws_when_producer_not_set(): void
    {
        $system = ComputerSystem::create('ERP', 'v1.0');

        $this->expectException(InvalidRecordException::class);
        $system->validate();
    }

    public function test_validate_passes_with_producer(): void
    {
        $system = RecordMother::computerSystem();

        // Should not throw
        $system->validate();
        $this->addToAssertionCount(1);
    }
}
