<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MoneyMathTest extends TestCase
{
    #[Test]
    public function percentages_use_basis_points_and_round_half_up(): void
    {
        $this->assertSame(1300, Money::percentage(6500, 2000));
        $this->assertSame(1625, Money::percentage(6500, 2500));
        $this->assertSame(1, Money::percentage(5, 1000));
        $this->assertSame(0, Money::percentage(4, 1000));
    }

    #[Test]
    public function prices_round_up_to_the_increment(): void
    {
        $this->assertSame(9500, Money::roundUp(9425, 100));
        $this->assertSame(9400, Money::roundUp(9400, 100));
        $this->assertSame(9425, Money::roundUp(9425, 1));
    }

    #[Test]
    public function allocation_always_sums_to_the_amount(): void
    {
        $parts = Money::allocate(1000, ['a' => 1, 'b' => 1, 'c' => 1]);
        $this->assertSame(1000, array_sum($parts));
        $this->assertSame([334, 333, 333], array_values($parts));

        $this->assertSame(['x' => 0, 'y' => 0], Money::allocate(500, ['x' => 0, 'y' => 0]));
        $this->assertSame(7, array_sum(Money::allocate(7, [9500, 3200, 600])));
    }
}
