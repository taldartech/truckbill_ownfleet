<?php

namespace App\Support;

use App\Branch;
use App\Trip;
use Illuminate\Support\Facades\DB;

class TripNumberGenerator
{
    public function preview(?string $tenantId = null, ?int $branchId = null): string
    {
        if ($tenantId === null || $branchId === null) {
            return $this->formatNumber('BR000', (int) now()->format('Y'), 1);
        }

        return $this->formatNumber(
            $this->branchCode($branchId),
            (int) now()->format('Y'),
            $this->nextSequence($tenantId, $branchId, (int) now()->format('Y')),
        );
    }

    public function generate(?string $tenantId, ?int $branchId): string
    {
        if ($tenantId === null) {
            throw new \InvalidArgumentException('Tenant context is required to generate a trip number.');
        }

        if ($branchId === null) {
            throw new \InvalidArgumentException('Branch context is required to generate a trip number.');
        }

        $year = (int) now()->format('Y');
        $branchCode = $this->branchCode($branchId);

        return DB::transaction(function () use ($tenantId, $branchId, $year, $branchCode): string {
            $sequence = $this->nextSequence($tenantId, $branchId, $year, lock: true);

            return $this->formatNumber($branchCode, $year, $sequence);
        });
    }

    private function nextSequence(string $tenantId, int $branchId, int $year, bool $lock = false): int
    {
        $prefix = sprintf('TRIP-%s-%d-', $this->branchCode($branchId), $year);

        $query = Trip::query()
            ->withoutGlobalScopes()
            ->withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->where('trip_number', 'like', $prefix.'%');

        if ($lock) {
            $query->lockForUpdate();
        }

        $lastNumber = $query
            ->orderByDesc('trip_number')
            ->value('trip_number');

        if ($lastNumber === null) {
            return $this->startingSequence($branchId);
        }

        $suffix = substr((string) $lastNumber, strlen($prefix));
        $lastSequence = is_numeric($suffix) ? (int) $suffix : 0;

        return $lastSequence + 1;
    }

    private function startingSequence(int $branchId): int
    {
        $start = Branch::query()
            ->whereKey($branchId)
            ->value('trip_sequence_start');

        return max(1, (int) ($start ?? 1));
    }

    private function branchCode(int $branchId): string
    {
        return sprintf('BR%03d', $branchId);
    }

    private function formatNumber(string $branchCode, int $year, int $sequence): string
    {
        return sprintf('TRIP-%s-%d-%s', $branchCode, $year, $this->formatSequence($sequence));
    }

    private function formatSequence(int $sequence): string
    {
        return str_pad((string) $sequence, max(4, strlen((string) $sequence)), '0', STR_PAD_LEFT);
    }
}
