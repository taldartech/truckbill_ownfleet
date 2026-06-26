<?php

namespace Tests\Unit;

use App\Helpers\ApiResponse;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_production_response_hides_debug_details(): void
    {
        Config::set('app.debug', false);
        Log::shouldReceive('error')->once();

        $response = ApiResponse::error(new Exception('Secret failure details'));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame([
            'success' => false,
            'message' => 'Unable to save. Please try again.',
        ], $response->getData(true));
    }

    public function test_debug_response_includes_debug_metadata(): void
    {
        Config::set('app.debug', true);
        Log::shouldReceive('error')->once();

        $response = ApiResponse::error(new Exception('Something broke'));

        $payload = $response->getData(true);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertFalse($payload['success']);
        $this->assertSame('Unable to save. Please try again.', $payload['message']);
        $this->assertArrayHasKey('debug', $payload);
        $this->assertSame('Exception', $payload['debug']['exception']);
        $this->assertSame('Something broke', $payload['debug']['message']);
        $this->assertArrayHasKey('file', $payload['debug']);
        $this->assertArrayHasKey('line', $payload['debug']);
        $this->assertArrayHasKey('trace_id', $payload['debug']);
    }

    public function test_query_exception_debug_includes_sql_message(): void
    {
        Config::set('app.debug', true);
        Log::shouldReceive('error')->once();

        $exception = new QueryException(
            'mysql',
            'insert into vehicle_maintenances (...) values (...)',
            [],
            new Exception('SQLSTATE[23000]: Integrity constraint violation'),
        );

        $response = ApiResponse::fromQueryException($exception);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('SQLSTATE[23000]', $response->getData(true)['debug']['message']);
    }
}
