<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_formats_inr_thousands(): void
    {
        $this->assertSame('₹5.8k', Money::label(5800, 'INR'));
    }

    public function test_null_is_dash(): void
    {
        $this->assertSame('—', Money::label(null, 'INR'));
    }
}
