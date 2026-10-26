<?php

namespace Tests\Unit\Support;

use App\Support\Money;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_round_rounds_to_two_decimal_places(): void
    {
        $this->assertSame(100.46, Money::round(100.456));
        $this->assertSame(100.45, Money::round(100.454));
        $this->assertSame(250.50, Money::round('250.5'));
    }

    public function test_format_formats_without_thousands_separator(): void
    {
        $this->assertSame('25164.24', Money::format(25164.24));
        $this->assertSame('0.00', Money::format(0));
        $this->assertSame('1500.00', Money::format('1500'));
    }

    public function test_display_includes_rupee_symbol_and_commas(): void
    {
        $this->assertSame('₹25,164.24', Money::display(25164.24));
        $this->assertSame('₹1,000,000.50', Money::display(1000000.5));
    }

    public function test_paise_conversion_accuracy(): void
    {
        $this->assertSame(10050, Money::toPaise(100.50));
        $this->assertSame(2516424, Money::toPaise('25164.24'));
        $this->assertSame(100.50, Money::fromPaise(10050));
        $this->assertSame(0.0, Money::fromPaise(0));
    }
}
