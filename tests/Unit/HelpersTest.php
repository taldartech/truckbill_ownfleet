<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class HelpersTest extends TestCase
{
    /** @test */
    public function format_date_formats_valid_date()
    {
        $date = '2023-01-15';
        $this->assertEquals('15 Jan 2023', formatDate($date));
    }

    /** @test */
    public function format_date_returns_default_for_null()
    {
        $this->assertEquals('—', formatDate(null));
    }

    /** @test */
    public function format_date_time_formats_valid_datetime()
    {
        $datetime = '2023-01-15 14:30:00';
        $this->assertEquals('15 Jan 2023 02:30 PM', formatDateTime($datetime));
    }

    /** @test */
    public function format_date_time_returns_default_for_invalid()
    {
        $this->assertEquals('—', formatDateTime('invalid-date'));
    }

    /** @test */
    public function format_carbon_date_handles_exceptions()
    {
        $this->assertEquals('—', formatCarbonDate('not-a-date', 'd M Y', '—'));
    }

    /** @test */
    public function get_indian_currency_formats_number_correctly()
    {
        $result = getIndianCurrency(1234.56);
        $this->assertStringContainsString('One Thousand Two Hundred', $result);
        $this->assertStringContainsString('Rupees', $result);
        $this->assertStringContainsString('Fifty Six Paise', $result);

        $resultSub21 = getIndianCurrency(1234.15);
        $this->assertStringContainsString('Fifteen Paise', $resultSub21);

        $resultOnlyPaise = getIndianCurrency(0.15);
        $this->assertSame(' Fifteen Paise', $resultOnlyPaise);

        $resultNoPaise = getIndianCurrency(100.00);
        $this->assertSame('One Hundred Rupees ', $resultNoPaise);
    }

    /** @test */
    public function versioned_asset_appends_config_version()
    {
        Config::set('app.asset_version', '999');
        $url = versioned_asset('css/app.css');
        $this->assertStringContainsString('css/app.css?v=999', $url);
    }
}
