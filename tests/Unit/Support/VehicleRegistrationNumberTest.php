<?php

namespace Tests\Unit\Support;

use App\Support\VehicleRegistrationNumber;
use Tests\TestCase;

class VehicleRegistrationNumberTest extends TestCase
{
    public function test_normalize_handles_null_spaces_and_symbols(): void
    {
        $this->assertNull(VehicleRegistrationNumber::normalize(null));
        $this->assertSame('RJ14AB1234', VehicleRegistrationNumber::normalize(' rj-14 ab 1234 '));
        $this->assertSame('', VehicleRegistrationNumber::normalize('   '));
    }

    public function test_is_valid_accepts_valid_indian_number_plates(): void
    {
        $this->assertTrue(VehicleRegistrationNumber::isValid('RJ14AB1234'));
        $this->assertTrue(VehicleRegistrationNumber::isValid('DL01A1234'));
        $this->assertTrue(VehicleRegistrationNumber::isValid('MH12DE1234'));
        $this->assertTrue(VehicleRegistrationNumber::isValid('22BH1234AA'));
        $this->assertTrue(VehicleRegistrationNumber::isValid('24BH5678Z'));
    }

    public function test_is_valid_rejects_invalid_registration_formats(): void
    {
        $this->assertFalse(VehicleRegistrationNumber::isValid(null));
        $this->assertFalse(VehicleRegistrationNumber::isValid(''));
        $this->assertFalse(VehicleRegistrationNumber::isValid('1234'));
        $this->assertFalse(VehicleRegistrationNumber::isValid('RJ'));
        $this->assertFalse(VehicleRegistrationNumber::isValid('RJ14TOOLONG12345'));
    }
}
