<?php

namespace App\Modules\TyreManagement\Services;

use App\Tyre;
use App\TyreAuditLog;
use App\Vehicle;

class TyreAuditLogger
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function log(
        string $eventType,
        ?Tyre $tyre = null,
        ?Vehicle $vehicle = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        array $payload = [],
        ?string $performedBy = null,
    ): TyreAuditLog {
        return TyreAuditLog::query()->create([
            'tyre_id' => $tyre?->id,
            'vehicle_id' => $vehicle?->id ?? $tyre?->activeInstallation?->vehicle_id,
            'event_type' => $eventType,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'payload' => $payload,
            'performed_by' => $performedBy,
            'performed_at' => now(),
        ]);
    }
}
