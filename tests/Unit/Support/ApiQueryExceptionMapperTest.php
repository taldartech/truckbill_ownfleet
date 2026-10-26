<?php

namespace Tests\Unit\Support;

use App\Support\ApiQueryExceptionMapper;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ApiQueryExceptionMapperTest extends TestCase
{
    private function createQueryException(string $message): QueryException
    {
        return new QueryException('mysql', 'INSERT INTO test VALUES (?)', [], new \Exception($message));
    }

    public function test_returns_null_for_non_integrity_query_exception(): void
    {
        $exception = $this->createQueryException('SQLSTATE[42S22]: Column not found: 1054 Unknown column');
        $this->assertNull(ApiQueryExceptionMapper::toValidationResponse($exception));
    }

    public function test_maps_duplicate_contact_number_violation(): void
    {
        $exception = $this->createQueryException('Duplicate entry 9876543210 for key branch_contact_number');
        $response = ApiQueryExceptionMapper::toValidationResponse($exception);

        $this->assertNotNull($response);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertSame('Please check the highlighted fields.', $data['message']);
        $this->assertSame(
            ['This contact number is already registered in your company.'],
            $data['errors']['contact_number']
        );
    }

    public function test_maps_duplicate_gst_number_violation(): void
    {
        $exception = $this->createQueryException('UNIQUE constraint failed: branches.gst_number');
        $response = ApiQueryExceptionMapper::toValidationResponse($exception);

        $this->assertNotNull($response);
        $data = $response->getData(true);
        $this->assertSame(
            ['This GST number is already registered in your company.'],
            $data['errors']['gst_number']
        );
    }

    public function test_maps_duplicate_pan_number_violation(): void
    {
        $exception = $this->createQueryException('duplicate key value violates unique constraint "pan_number"');
        $response = ApiQueryExceptionMapper::toValidationResponse($exception);

        $this->assertNotNull($response);
        $data = $response->getData(true);
        $this->assertSame(
            ['This PAN number is already registered in your company.'],
            $data['errors']['pan_number']
        );
    }

    public function test_maps_duplicate_trip_number_violation(): void
    {
        $exception = $this->createQueryException('Integrity constraint violation: 1062 Duplicate entry trips_tenant_branch_trip_number_unique');
        $response = ApiQueryExceptionMapper::toValidationResponse($exception);

        $this->assertNotNull($response);
        $data = $response->getData(true);
        $this->assertSame(
            ['This trip number is already used for this branch.'],
            $data['errors']['trip_number']
        );
    }

    public function test_maps_duplicate_invoice_number_violation(): void
    {
        $exception = $this->createQueryException('Duplicate entry for key invoices_tenant_id_branch_id_invoice_number_unique');
        $response = ApiQueryExceptionMapper::toValidationResponse($exception);

        $this->assertNotNull($response);
        $data = $response->getData(true);
        $this->assertSame(
            ['This invoice number is already used for this branch.'],
            $data['errors']['invoice_number']
        );
    }

    public function test_maps_duplicate_broker_or_customer_name_violation(): void
    {
        $exception = $this->createQueryException('Integrity constraint violation: Duplicate entry for brokers_tenant_id_name_unique');
        $response = ApiQueryExceptionMapper::toValidationResponse($exception);

        $this->assertNotNull($response);
        $data = $response->getData(true);
        $this->assertSame(
            ['This name is already used in the selected city.'],
            $data['errors']['name']
        );

        // Generic fallback when table mentions brokers or customers without specific index needle
        $genericException = $this->createQueryException('Duplicate entry on table customers');
        $genericResponse = ApiQueryExceptionMapper::toValidationResponse($genericException);
        $this->assertNotNull($genericResponse);
        $genericData = $genericResponse->getData(true);
        $this->assertSame(
            ['This name is already used in the selected city.'],
            $genericData['errors']['name']
        );
    }

    public function test_returns_null_when_integrity_violation_has_unrecognized_key(): void
    {
        $exception = $this->createQueryException('Integrity constraint violation: 1062 Duplicate entry for key unknown_idx');
        $response = ApiQueryExceptionMapper::toValidationResponse($exception);

        $this->assertNull($response);
    }
}
