<?php

namespace Tests\Unit\Support\Address;

use App\Support\Address\AddressCoordinatePolicy;
use App\Support\Address\AddressPrecision;
use PHPUnit\Framework\TestCase;

class AddressCoordinatePolicyTest extends TestCase
{
    public function test_field_geocode_cannot_overwrite_exact_point(): void
    {
        $this->assertFalse(AddressCoordinatePolicy::shouldAcceptFieldGeocode(AddressPrecision::Exact));
        $this->assertTrue(AddressCoordinatePolicy::shouldAcceptFieldGeocode(AddressPrecision::Approx));
        $this->assertTrue(AddressCoordinatePolicy::shouldAcceptFieldGeocode(AddressPrecision::None));
    }

    public function test_manual_point_selection_and_address_book_coordinates_are_exact(): void
    {
        $this->assertSame(
            AddressPrecision::Exact,
            AddressCoordinatePolicy::precisionForManualPointSelection(50.45, 30.52)
        );

        $this->assertSame(
            AddressPrecision::Exact,
            AddressCoordinatePolicy::precisionForAddressBook(50.45, 30.52)
        );
    }

    public function test_no_coords_state_is_none(): void
    {
        $this->assertSame(AddressPrecision::None, AddressCoordinatePolicy::precisionForFieldGeocode(null, null));
    }
}
