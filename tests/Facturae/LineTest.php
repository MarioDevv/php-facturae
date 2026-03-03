<?php
declare(strict_types=1);
namespace MarioDevv\Rex\Tests\Facturae;
use MarioDevv\Rex\Facturae\Entities\Line;
use PHPUnit\Framework\TestCase;

final class LineTest extends TestCase
{
    public function test_gross_amount(): void
    {
        $this->assertSame(60.42, (new Line('Test', 3, 20.14, []))->grossAmount());
    }

    public function test_gross_amount_with_discount(): void
    {
        $this->assertSame(90.0, (new Line('Test', 1, 100.0, [], discount: 10.0))->grossAmount());
    }

    public function test_fractional_discount(): void
    {
        $this->assertSame(63.33, (new Line('Test', 2, 33.33, [], discount: 5.0))->grossAmount());
    }
}
