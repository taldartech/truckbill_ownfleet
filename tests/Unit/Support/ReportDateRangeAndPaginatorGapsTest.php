<?php

namespace Tests\Unit\Support;

use App\Support\ReportDateRange;
use App\Support\ReportPaginator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReportDateRangeAndPaginatorGapsTest extends TestCase
{
    public function test_report_date_range_assert_valid_validates_required_array_keys(): void
    {
        $this->expectException(ValidationException::class);
        ReportDateRange::assertValid(['from' => '', 'to' => '']);
    }

    public function test_report_paginator_paginate_and_edge_slicing(): void
    {
        $request = Request::create('/reports', 'GET', ['page' => 50, 'per_page' => 10]);

        $response = ReportPaginator::paginate($request, [
            ['s_no' => 1, 'name' => 'Trip 1'],
            ['s_no' => 2, 'name' => 'Trip 2'],
        ], ['grand_total' => 5000]);

        $data = $response->getData(true);
        $this->assertSame(5000, $data['grand_total']);
        $this->assertSame(1, $data['meta']['current_page']); // Clamped from 50 to 1

        // Empty slicing edge case
        [$slice, $meta] = ReportPaginator::slice($request, []);
        $this->assertSame(0, $meta['from']);
        $this->assertSame(0, $meta['to']);
    }
}
