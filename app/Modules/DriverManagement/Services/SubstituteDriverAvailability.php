<?php

namespace App\Modules\DriverManagement\Services;

use App\Attendance;
use App\Enums\TripStatus;
use App\Support\CurrentTenant;
use App\Trip;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SubstituteDriverAvailability
{
    public function assertAvailable(string $date, string $substituteDriverId, ?string $exceptAttendanceId = null): void
    {
        $message = $this->conflictMessage($date, $substituteDriverId, $exceptAttendanceId);

        if ($message !== null) {
            throw ValidationException::withMessages([
                'temporary_driver_id' => [$message],
            ]);
        }
    }

    public function conflictMessage(string $date, string $substituteDriverId, ?string $exceptAttendanceId = null): ?string
    {
        $tenantId = CurrentTenant::requireId();

        $substituteAttendance = Attendance::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('attendance_date', $date)
            ->where('temporary_driver_id', $substituteDriverId)
            ->when($exceptAttendanceId !== null, fn ($query) => $query->whereKeyNot($exceptAttendanceId))
            ->with('vehicle:id,registration_number')
            ->first();

        if ($substituteAttendance !== null) {
            $vehicleLabel = $substituteAttendance->vehicle?->registration_number ?? 'another truck';

            return "This driver is already working as substitute on {$vehicleLabel} for this date.";
        }

        $openTrip = $this->findOpenTripForDriverOnDate($substituteDriverId, $date);

        if ($openTrip !== null) {
            $vehicleLabel = $openTrip->vehicle?->registration_number ?? 'another truck';
            $tripLabel = $openTrip->trip_number ?? 'a trip';

            return "This driver is on {$tripLabel} ({$vehicleLabel}) for this date.";
        }

        return null;
    }

    /**
     * @return array<string, array<string, array{kind: string, vehicle_label: string, trip_number: string|null}>>
     */
    public function openTripBusyByDate(string $fromDate, string $toDate): array
    {
        $tenantId = CurrentTenant::requireId();
        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->startOfDay();

        $trips = Trip::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [
                TripStatus::Created->value,
                TripStatus::InProgress->value,
            ])
            ->whereRaw('DATE(start_at) <= ?', [$to->toDateString()])
            ->where(function ($query) use ($from): void {
                $query->whereNull('end_at')
                    ->orWhereRaw('DATE(end_at) >= ?', [$from->toDateString()]);
            })
            ->with('vehicle:id,registration_number')
            ->get(['id', 'vehicle_id', 'driver_id', 'substitute_driver_id', 'trip_number', 'start_at', 'end_at']);

        /** @var array<string, array<string, array{kind: string, vehicle_label: string, trip_number: string|null}>> $busyByDate */
        $busyByDate = [];

        foreach ($trips as $trip) {
            $tripStart = Carbon::parse($trip->start_at)->startOfDay();
            $tripEnd = $trip->end_at !== null
                ? Carbon::parse($trip->end_at)->startOfDay()
                : $to->copy();

            $rangeStart = $tripStart->greaterThan($from) ? $tripStart : $from->copy();
            $rangeEnd = $tripEnd->lessThan($to) ? $tripEnd : $to->copy();

            $vehicleLabel = $trip->vehicle?->registration_number ?? '—';
            $entry = [
                'kind' => 'trip',
                'vehicle_label' => $vehicleLabel,
                'trip_number' => $trip->trip_number,
            ];

            for ($cursor = $rangeStart->copy(); $cursor->lte($rangeEnd); $cursor->addDay()) {
                $dateKey = $cursor->toDateString();

                if (! isset($busyByDate[$dateKey])) {
                    $busyByDate[$dateKey] = [];
                }

                foreach (array_filter([(string) $trip->driver_id, (string) $trip->substitute_driver_id]) as $driverId) {
                    $busyByDate[$dateKey][$driverId] = $entry;
                }
            }
        }

        return $busyByDate;
    }

    private function findOpenTripForDriverOnDate(string $driverId, string $date): ?Trip
    {
        $tenantId = CurrentTenant::requireId();

        return Trip::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [
                TripStatus::Created->value,
                TripStatus::InProgress->value,
            ])
            ->where(function ($query) use ($driverId): void {
                $query->where('driver_id', $driverId)
                    ->orWhere('substitute_driver_id', $driverId);
            })
            ->whereRaw('DATE(start_at) <= ?', [$date])
            ->where(function ($query) use ($date): void {
                $query->whereNull('end_at')
                    ->orWhereRaw('DATE(end_at) >= ?', [$date]);
            })
            ->with('vehicle:id,registration_number')
            ->first();
    }
}
