<?php
declare(strict_types=1);

namespace PhpFacturae\Tests;
use PhpFacturae\Party;
use PhpFacturae\Exceptions\InvalidPostalCodeException;
use PHPUnit\Framework\TestCase;

final class PartyTest extends TestCase
{
    public function test_creates_company(): void
    {
        $party = Party::company('A00000000', 'Test S.L.');
        $this->assertTrue($party->isLegalEntity());
        $this->assertSame('A00000000', $party->taxNumber());
    }

    public function test_creates_person(): void
    {
        $party = Party::person('00000000A', 'Juan', 'Garcia', 'Lopez');
        $this->assertFalse($party->isLegalEntity());
        $this->assertSame('Juan', $party->name());
        $this->assertSame('Garcia', $party->firstSurname());
        $this->assertSame('Lopez', $party->lastSurname());
    }

    public function test_rejects_invalid_postal_code(): void
    {
        $this->expectException(InvalidPostalCodeException::class);
        Party::company('A00000000', 'Test S.L.')->address('C/ Test', '123', 'Madrid', 'Madrid');
    }

    public function test_tax_number_uppercased(): void
    {
        $this->assertSame('X1234567A', Party::person('x1234567a', 'Test', 'User')->taxNumber());
    }

    public function test_fluent_contact(): void
    {
        $party = Party::company('A00000000', 'Test S.L.')
            ->address('C/ Test', '28001', 'Madrid', 'Madrid')
            ->email('test@test.com')
            ->phone('910000000');
        $this->assertSame('test@test.com', $party->getEmail());
        $this->assertSame('910000000', $party->getPhone());
    }
}
