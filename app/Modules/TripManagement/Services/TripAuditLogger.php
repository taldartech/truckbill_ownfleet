<?php

namespace App\Modules\TripManagement\Services;

use App\Trip;
use App\TripAuditLog;

class TripAuditLogger
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function log(
        string $eventType,
        ?Trip $trip = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        array $payload = [],
        ?string $performedBy = null,
    ): TripAuditLog {
        return TripAuditLog::query()->create([
            'tenant_id' => $trip?->tenant_id,
            'branch_id' => $trip?->branch_id,
            'trip_id' => $trip?->id,
            'event_type' => $eventType,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'payload' => $payload,
            'performed_by' => $performedBy,
            'performed_at' => now(),
        ]);
    }
}
