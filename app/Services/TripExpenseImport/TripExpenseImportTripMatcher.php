<?php

namespace App\Services\TripExpenseImport;

use App\Enums\TripStatus;
use App\Support\VehicleRegistrationNumber;
use App\Trip;
use App\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class TripExpenseImportTripMatcher
{
    /**
     * @return Collection<int, Trip>
     */
    public function candidateTrips(string $tenantId, CarbonImmutable $statementDateIst): Collection
    {
        $statementDay = $statementDateIst->startOfDay();
        $windowStart = $statementDay->subDays(7)->startOfDay();

        return Trip::query()
            ->with('vehicle:id,registration_number')
            ->where('tenant_id', $tenantId)
            ->whereNull('cancelled_at')
            ->where('status', '!=', TripStatus::Cancelled->value)
            ->whereNotNull('start_at')
            ->whereDate('start_at', '<=', $statementDay->toDateString())
            ->where(function ($query) use ($windowStart) {
                $query->whereNull('end_at')
                    ->orWhereDate('end_at', '>=', $windowStart->toDateString());
            })
            ->orderByDesc('start_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  Collection<int, Trip>  $candidates
     * @return list<array{id: string, vehicle_id: string|null, label: string}>
     */
    public function tripOptions(Collection $candidates): array
    {
        return $candidates->map(function (Trip $trip) {
            $start = $trip->start_at
                ? CarbonImmutable::parse($trip->start_at)->timezone(StatementDateParser::TIMEZONE)->format('j M')
                : '';
            $truck = $trip->vehicle?->registration_number ?? '—';

            return [
                'id' => (string) $trip->id,
                'vehicle_id' => $trip->vehicle_id ? (string) $trip->vehicle_id : null,
                'label' => trim(($trip->trip_number ?? '').' · '.$truck.' · '.$start, ' ·'),
            ];
        })->values()->all();
    }

    /**
     * @return array{vehicle_id: string|null, vehicle_label: string|null, issue: string|null}
     */
    public function resolveVehicle(string $tenantId, ?string $vrn): array
    {
        $normalized = VehicleRegistrationNumber::normalize($vrn);

        if ($normalized === null || $normalized === '') {
            return [
                'vehicle_id' => null,
                'vehicle_label' => null,
                'issue' => 'No vehicle on statement — choose a trip',
            ];
        }

        $vehicle = Vehicle::query()
            ->where('tenant_id', $tenantId)
            ->where('registration_number', $normalized)
            ->first();

        if (! $vehicle) {
            return [
                'vehicle_id' => null,
                'vehicle_label' => $normalized,
                'issue' => 'Vehicle not found',
            ];
        }

        return [
            'vehicle_id' => (string) $vehicle->id,
            'vehicle_label' => $vehicle->registration_number,
            'issue' => null,
        ];
    }

    /**
     * @param  Collection<int, Trip>  $candidates
     */
    public function matchTrip(?string $vehicleId, CarbonImmutable $transactedAtIst, Collection $candidates): ?Trip
    {
        if ($vehicleId === null || $vehicleId === '') {
            return null;
        }

        $txnUtc = $transactedAtIst->utc();
        $txnDate = $transactedAtIst->toDateString();

        $matches = $candidates->filter(function (Trip $trip) use ($vehicleId, $txnUtc, $txnDate) {
            if ((string) $trip->vehicle_id !== (string) $vehicleId) {
                return false;
            }

            if (! $trip->start_at) {
                return false;
            }

            $start = CarbonImmutable::parse($trip->start_at);
            if ($start->gt($txnUtc)) {
                return false;
            }

            if ($trip->actual_arrival_at) {
                $arrival = CarbonImmutable::parse($trip->actual_arrival_at);

                return $arrival->gte($txnUtc);
            }

            if ($trip->end_at === null) {
                return true;
            }

            return CarbonImmutable::parse($trip->end_at)->timezone(StatementDateParser::TIMEZONE)->toDateString() >= $txnDate;
        });

        if ($matches->isEmpty()) {
            return null;
        }

        return $matches
            ->sort(function (Trip $a, Trip $b) use ($txnUtc) {
                $aArrival = $a->actual_arrival_at ? CarbonImmutable::parse($a->actual_arrival_at) : null;
                $bArrival = $b->actual_arrival_at ? CarbonImmutable::parse($b->actual_arrival_at) : null;

                if ($aArrival && $bArrival) {
                    $cmp = $aArrival->diffInSeconds($txnUtc, false) <=> $bArrival->diffInSeconds($txnUtc, false);
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                } elseif ($aArrival && ! $bArrival) {
                    return -1;
                } elseif (! $aArrival && $bArrival) {
                    return 1;
                }

                $startCmp = CarbonImmutable::parse($b->start_at)->timestamp <=> CarbonImmutable::parse($a->start_at)->timestamp;
                if ($startCmp !== 0) {
                    return $startCmp;
                }

                return strcmp((string) $b->id, (string) $a->id);
            })
            ->first();
    }

    /**
     * @param  Collection<int, Trip>  $candidates
     * @return array<string, true>
     */
    public function existingReferences(string $tenantId, array $references): array
    {
        $refs = array_values(array_filter(array_unique(array_map(
            static fn ($ref) => trim((string) $ref),
            $references
        )), static fn ($ref) => $ref !== ''));

        if ($refs === []) {
            return [];
        }

        return \App\TripExpense::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('reference', $refs)
            ->pluck('reference')
            ->mapWithKeys(static fn ($ref) => [(string) $ref => true])
            ->all();
    }
}
