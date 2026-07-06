<?php

namespace App\Support;

use App\Driver;
use App\User;
use App\VehicleDocument;
use Illuminate\Support\Carbon;

class FleetComplianceAlerts
{
    private const DOCUMENT_EXPIRY_WINDOW_DAYS = 30;

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function build(User $user): array
    {
        $today = Carbon::today();
        $documents = [];
        $licenses = [];

        if ($user->hasPermission('vehicle_documents.view')) {
            $documentQuery = VehicleDocument::query()
                ->with('vehicle:id,registration_number')
                ->whereNotNull('expiry_date')
                ->where(function ($query) use ($today) {
                    $query->whereDate('expiry_date', '<', $today->toDateString())
                        ->orWhere(function ($expiringQuery) use ($today) {
                            $expiringQuery
                                ->whereDate('expiry_date', '>=', $today->toDateString())
                                ->whereDate(
                                    'expiry_date',
                                    '<=',
                                    $today->copy()->addDays(self::DOCUMENT_EXPIRY_WINDOW_DAYS)->toDateString(),
                                );
                        });
                });

            foreach ($documentQuery->get() as $document) {
                $documents[] = self::formatDocumentAlert($document, $today);
            }

            $documents = VehicleDocumentStatus::sortByUrgency($documents);
        }

        if ($user->hasPermission('drivers.view')) {
            $driverQuery = Driver::query()
                ->where('is_active', true)
                ->whereNotNull('license_expiry_date')
                ->whereDate(
                    'license_expiry_date',
                    '<=',
                    $today->copy()->addDays(self::DOCUMENT_EXPIRY_WINDOW_DAYS)->toDateString(),
                );

            foreach ($driverQuery->get(['id', 'name', 'license_number', 'license_expiry_date']) as $driver) {
                $expiryDate = Carbon::parse($driver->license_expiry_date)->startOfDay();
                $daysRemaining = (int) $today->diffInDays($expiryDate, false);
                $isExpired = $expiryDate->lt($today);
                $status = VehicleDocumentStatus::compute($expiryDate, self::DOCUMENT_EXPIRY_WINDOW_DAYS, $today);

                $licenses[] = [
                    'driver_id' => $driver->id,
                    'driver_name' => $driver->name,
                    'license_number' => $driver->license_number,
                    'license_expiry_date' => $expiryDate->toDateString(),
                    'status' => $isExpired ? 'expired' : 'expiring',
                    'days_remaining' => $daysRemaining,
                    ...$status,
                ];
            }

            $licenses = VehicleDocumentStatus::sortByUrgency($licenses);
        }

        return [
            'documents' => $documents,
            'licenses' => $licenses,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function formatDocumentAlert(VehicleDocument $document, Carbon $today): array
    {
        $expiryDate = $document->expiry_date?->copy()->startOfDay();
        $isExpired = $expiryDate !== null && $expiryDate->lt($today);
        $code = (string) $document->document_type;
        $enumType = \App\Enums\VehicleDocumentType::tryFrom($code);
        $label = \App\Support\DocumentTypeDefaults::labelFor((string) $document->tenant_id, $code)
            ?? $enumType?->label()
            ?? $code;

        $status = VehicleDocumentStatus::compute(
            $expiryDate,
            $document->reminder_days_before,
            $today,
        );

        return [
            'id' => $document->id,
            'vehicle_id' => $document->vehicle_id,
            'vehicle_registration' => $document->vehicle?->registration_number,
            'document_type' => $code,
            'document_type_label' => $label,
            'expiry_date' => $expiryDate?->toDateString(),
            'reminder_days_before' => $document->reminder_days_before,
            'status' => $isExpired ? 'expired' : 'expiring',
            ...$status,
        ];
    }
}
