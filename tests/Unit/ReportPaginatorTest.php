<?php

namespace Tests\Unit;

use App\Support\ReportPaginator;
use Illuminate\Http\Request;
use Tests\TestCase;

class ReportPaginatorTest extends TestCase
{
    public function test_slice_returns_requested_page_with_meta(): void
    {
        $rows = array_map(
            fn (int $number): array => ['s_no' => $number, 'value' => "row-{$number}"],
            range(1, 30),
        );

        $request = Request::create('/reports', 'GET', [
            'page' => 2,
            'per_page' => 10,
        ]);

        [$slice, $meta] = ReportPaginator::slice($request, $rows);

        $this->assertCount(10, $slice);
        $this->assertSame(11, $slice[0]['s_no']);
        $this->assertSame(20, $slice[9]['s_no']);
        $this->assertSame(2, $meta['current_page']);
        $this->assertSame(3, $meta['last_page']);
        $this->assertSame(10, $meta['per_page']);
        $this->assertSame(30, $meta['total']);
        $this->assertSame(11, $meta['from']);
        $this->assertSame(20, $meta['to']);
    }

    public function test_per_page_falls_back_to_default_for_invalid_values(): void
    {
        $request = Request::create('/reports', 'GET', ['per_page' => 15]);

        $this->assertSame(ReportPaginator::DEFAULT_PER_PAGE, ReportPaginator::perPage($request));
    }

    public function test_extract_grand_total_row_separates_summary_row(): void
    {
        $rows = [
            ['service_type' => 'OIL', 'total_cost' => 100],
            ['service_type' => '_grand_total', 'total_cost' => 100],
        ];

        [$data, $grandTotal] = ReportPaginator::extractGrandTotalRow($rows);

        $this->assertCount(1, $data);
        $this->assertSame('OIL', $data[0]['service_type']);
        $this->assertSame('_grand_total', $grandTotal['service_type']);
    }
}
