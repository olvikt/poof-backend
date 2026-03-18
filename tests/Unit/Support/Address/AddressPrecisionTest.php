<?php

namespace Tests\Unit\Support\Address;

use App\Support\Address\AddressPrecision;
use PHPUnit\Framework\TestCase;

class AddressPrecisionTest extends TestCase
{
    public function test_it_interprets_none_approx_and_exact_states(): void
    {
        $this->assertTrue(AddressPrecision::fromNullable(null)->isNone());
        $this->assertTrue(AddressPrecision::fromNullable('approx')->isApprox());
        $this->assertTrue(AddressPrecision::fromNullable('exact')->isExact());
    }

    public function test_it_derives_none_when_coordinates_are_missing(): void
    {
        $this->assertSame(AddressPrecision::None, AddressPrecision::fromCoordinates(null, 30.52));
        $this->assertSame(AddressPrecision::None, AddressPrecision::fromCoordinates(50.45, null));
        $this->assertSame(AddressPrecision::None, AddressPrecision::fromCoordinates(null, null, true));
    }

    public function test_it_derives_approx_and_exact_from_coordinate_origin(): void
    {
        $this->assertSame(AddressPrecision::Approx, AddressPrecision::fromCoordinates(50.45, 30.52));
        $this->assertSame(AddressPrecision::Exact, AddressPrecision::fromCoordinates(50.45, 30.52, true));
    }
}
