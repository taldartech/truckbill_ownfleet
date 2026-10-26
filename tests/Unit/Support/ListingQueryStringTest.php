<?php

namespace Tests\Unit\Support;

use App\Support\ListingQueryString;
use Illuminate\Http\Request;
use Tests\TestCase;

class ListingQueryStringTest extends TestCase
{
    public function test_params_whitelists_only_allowed_keys(): void
    {
        $request = Request::create('/trips', 'GET', [
            'search' => 'RJ14',
            'status' => 'active',
            'disallowed_filter' => 'malicious_input',
        ]);

        $params = ListingQueryString::params($request, ['search', 'status']);

        $this->assertSame(['search' => 'RJ14', 'status' => 'active'], $params);
    }

    public function test_params_excludes_empty_and_null_values(): void
    {
        $request = Request::create('/trips', 'GET', [
            'search' => '',
            'status' => null,
            'page' => '1',
        ]);

        $params = ListingQueryString::params($request, ['search', 'status', 'page']);

        $this->assertSame(['page' => '1'], $params);
    }

    public function test_params_ignores_nested_arrays_and_objects(): void
    {
        $request = Request::create('/trips', 'GET', [
            'search' => ['injection' => 'value'],
            'status' => 'completed',
        ]);

        $params = ListingQueryString::params($request, ['search', 'status']);

        $this->assertSame(['status' => 'completed'], $params);
    }

    public function test_current_suffix_formats_http_query_or_empty_string(): void
    {
        $emptyRequest = Request::create('/trips', 'GET', []);
        $this->app->instance('request', $emptyRequest);
        $this->assertSame('', ListingQueryString::currentSuffix(['search']));

        $activeRequest = Request::create('/trips', 'GET', ['search' => 'Truck1']);
        $this->app->instance('request', $activeRequest);
        $this->assertSame('?search=Truck1', ListingQueryString::currentSuffix(['search']));
    }

    public function test_scalar_value_returns_string_for_allowed_keys_and_empty_for_disallowed(): void
    {
        $request = Request::create('/trips', 'GET', [
            'search' => 'MH04',
            'injected' => 'danger',
            'array_field' => ['bad'],
        ]);

        $this->assertSame('MH04', ListingQueryString::scalarValue($request, 'search', ['search']));
        $this->assertSame('', ListingQueryString::scalarValue($request, 'injected', ['search']));
        $this->assertSame('', ListingQueryString::scalarValue($request, 'array_field', ['array_field']));
        $this->assertSame('', ListingQueryString::scalarValue($request, 'non_existent', ['non_existent']));
    }
}
