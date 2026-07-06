<?php

namespace Tests\Unit;

use App\Support\ReportDateRange;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReportDateRangeTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_accepts_valid_date_ranges_within_three_months(): void
    {
        Carbon::setTestNow('2026-07-06');

        ReportDateRange::assertValidDates('2026-04-07', '2026-07-06', 'from_date', 'to_date');

        $this->assertTrue(true);
    }

    public function test_it_rejects_future_from_date(): void
    {
        Carbon::setTestNow('2026-07-06');

        try {
            ReportDateRange::assertValidDates('2026-07-07', '2026-07-07', 'from_date', 'to_date');
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Future date is not allowed.',
                $exception->errors()['from_date'][0],
            );
        }
    }

    public function test_it_rejects_future_to_date(): void
    {
        Carbon::setTestNow('2026-07-06');

        try {
            ReportDateRange::assertValidDates('2026-07-01', '2026-07-31', 'from_date', 'to_date');
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Future date is not allowed.',
                $exception->errors()['to_date'][0],
            );
        }
    }

    public function test_it_rejects_to_date_before_from_date(): void
    {
        Carbon::setTestNow('2026-07-06');

        try {
            ReportDateRange::assertValidDates('2026-07-06', '2026-07-01', 'from_date', 'to_date');
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'To Date cannot be earlier than From Date.',
                $exception->errors()['to_date'][0],
            );
        }
    }

    public function test_it_rejects_ranges_longer_than_three_months(): void
    {
        Carbon::setTestNow('2026-07-06');

        try {
            ReportDateRange::assertValidDates('2026-01-01', '2026-07-06', 'from_date', 'to_date');
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Maximum allowed date range is 3 months.',
                $exception->errors()['from_date'][0],
            );
        }
    }
}
