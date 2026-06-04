<?php

namespace App\Support;

use App\Trip;
use Illuminate\Support\Facades\DB;

class TripNumberGenerator
{
    public function preview(?string $tenantId = null): string
    {
        if ($tenantId === null) {
            return $this->formatNumber((int) now()->format('Y'), 1);
        }

        return $this->formatNumber(
            (int) now()->format('Y'),
            $this->nextSequence($tenantId, (int) now()->format('Y')),
        );
    }

    public function generate(?string $tenantId): string
    {
        if ($tenantId === null) {
            throw new \InvalidArgumentException('Tenant context is required to generate a trip number.');
        }

        $year = (int) now()->format('Y');

        return DB::transaction(function () use ($tenantId, $year): string {
            $sequence = $this->nextSequence($tenantId, $year, lock: true);

            return $this->formatNumber($year, $sequence);
        });
    }

    private function nextSequence(string $tenantId, int $year, bool $lock = false): int
    {
        $prefix = sprintf('TRIP-%d-', $year);

        $query = Trip::query()
            ->where('tenant_id', $tenantId)
            ->where('trip_number', 'like', $prefix.'%');

        if ($lock) {
            $query->lockForUpdate();
        }

        $lastNumber = $query
            ->orderByDesc('trip_number')
            ->value('trip_number');

        if ($lastNumber === null) {
            return 1;
        }

        $suffix = substr((string) $lastNumber, strlen($prefix));
        $lastSequence = is_numeric($suffix) ? (int) $suffix : 0;

        return $lastSequence + 1;
    }

    private function formatNumber(int $year, int $sequence): string
    {
        return sprintf('TRIP-%d-%06d', $year, $sequence);
    }
}
