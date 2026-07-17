<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiQueryExceptionMapper
{
    public static function toValidationResponse(QueryException $exception): ?JsonResponse
    {
        $message = $exception->getMessage();
        $errors = self::mapIntegrityViolationErrors($message);

        if ($errors === []) {
            return null;
        }

        return response()->json([
            'message' => 'Please check the highlighted fields.',
            'errors' => $errors,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function mapIntegrityViolationErrors(string $message): array
    {
        if (! self::isIntegrityViolation($message)) {
            return [];
        }

        $normalized = strtolower($message);

        foreach (self::fieldMatchers() as $field => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($normalized, strtolower($needle))) {
                    return [
                        $field => [self::fieldMessage($field)],
                    ];
                }
            }
        }

        if (str_contains($normalized, 'brokers') || str_contains($normalized, 'customers')) {
            return [
                'name' => [self::fieldMessage('name')],
            ];
        }

        return [];
    }

    private static function isIntegrityViolation(string $message): bool
    {
        return str_contains($message, 'UNIQUE constraint failed')
            || str_contains($message, 'Duplicate entry')
            || str_contains($message, 'duplicate key value')
            || str_contains($message, 'Integrity constraint violation');
    }

    /**
     * @return array<string, list<string>>
     */
    private static function fieldMatchers(): array
    {
        return [
            'contact_number' => ['contact_number', 'branch_contact_number'],
            'gst_number' => ['gst_number'],
            'pan_number' => ['pan_number'],
            'trip_number' => [
                'trips_tenant_branch_trip_number_unique',
                'trips.tenant_id, trips.branch_id, trips.trip_number',
                'trip_number',
            ],
            'invoice_number' => [
                'invoices_tenant_id_branch_id_invoice_number_unique',
                'invoices.tenant_id, invoices.branch_id, invoices.invoice_number',
                'invoice_number',
            ],
            'name' => [
                'brokers.tenant_id, brokers.name',
                'customers.tenant_id, customers.name',
                'brokers.tenant_id, brokers.state_id, brokers.city_id, brokers.name',
                'customers.tenant_id, customers.state_id, customers.city_id, customers.name',
                'brokers_tenant_id_name_unique',
                'customers_tenant_id_name_unique',
                'brokers_tenant_id_state_id_city_id_name_unique',
                'customers_tenant_id_state_id_city_id_name_unique',
            ],
        ];
    }

    private static function fieldMessage(string $field): string
    {
        return match ($field) {
            'contact_number' => 'This contact number is already registered in your company.',
            'gst_number' => 'This GST number is already registered in your company.',
            'pan_number' => 'This PAN number is already registered in your company.',
            'trip_number', 'trip_sequence' => 'This trip number is already used for this branch.',
            'invoice_number', 'invoice_sequence' => 'This invoice number is already used for this branch.',
            default => 'This name is already used in the selected city.',
        };
    }
}
