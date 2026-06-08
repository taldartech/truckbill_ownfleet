<?php

namespace App\Modules\TyreManagement\Services;

use App\Enums\TyreExpenseType;
use App\Tyre;
use App\TyreExpense;
use App\TyreInstallation;
use App\TyreReplacement;
use App\TyreRetread;
use Illuminate\Support\Facades\DB;

class TyreReportService
{
    /**
     * @param  array{vehicle_id?: string, from?: string, to?: string}  $filters
     * @return list<array<string, mixed>>
     */
    public function tyreLife(array $filters = []): array
    {
        return Tyre::query()
            ->when($filters['vehicle_id'] ?? null, function ($query, string $vehicleId): void {
                $query->whereHas('installations', fn ($q) => $q->where('vehicle_id', $vehicleId));
            })
            ->withSum('installations as total_running_km', 'running_km')
            ->orderBy('serial_number')
            ->get()
            ->map(fn (Tyre $tyre) => [
                'tyre_id' => $tyre->id,
                'serial_number' => $tyre->serial_number,
                'brand' => $tyre->brand,
                'status' => $tyre->status?->value,
                'current_km' => (float) $tyre->current_km,
                'total_running_km' => (float) ($tyre->total_running_km ?? 0),
                'purchase_date' => $tyre->purchase_date?->toDateString(),
            ])
            ->all();
    }

    /**
     * @param  array{from?: string, to?: string}  $filters
     * @return list<array<string, mixed>>
     */
    public function costPerKm(array $filters = []): array
    {
        return Tyre::query()
            ->with(['expenses', 'installations'])
            ->get()
            ->map(function (Tyre $tyre): array {
                $totalCost = (float) $tyre->expenses->sum('amount') + (float) $tyre->purchase_cost;
                $scrapRecovery = (float) ($tyre->scrap_sale_amount ?? 0);
                $netCost = max(0, $totalCost - $scrapRecovery);
                $installationKm = (float) $tyre->installations->sum('running_km');
                $totalKm = max(1, (float) $tyre->current_km + $installationKm);

                return [
                    'tyre_id' => $tyre->id,
                    'serial_number' => $tyre->serial_number,
                    'total_cost' => round($netCost, 2),
                    'total_km' => round($totalKm, 2),
                    'cost_per_km' => round($netCost / $totalKm, 4),
                    'scrap_recovery' => round($scrapRecovery, 2),
                ];
            })
            ->sortBy('cost_per_km')
            ->values()
            ->all();
    }

    /**
     * @param  array{from?: string, to?: string, vehicle_id?: string}  $filters
     * @return list<array<string, mixed>>
     */
    public function replacements(array $filters = []): array
    {
        return TyreReplacement::query()
            ->when($filters['vehicle_id'] ?? null, fn ($q, string $id) => $q->where('vehicle_id', $id))
            ->when($filters['from'] ?? null, fn ($q, string $from) => $q->whereDate('replaced_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, string $to) => $q->whereDate('replaced_at', '<=', $to))
            ->with(['vehicle', 'oldTyre', 'newTyre'])
            ->orderByDesc('replaced_at')
            ->get()
            ->map(fn (TyreReplacement $row) => [
                'id' => $row->id,
                'replaced_at' => $row->replaced_at?->toDateString(),
                'vehicle' => $row->vehicle?->registration_number,
                'position' => $row->position_code?->value ?? $row->position_code,
                'old_serial' => $row->oldTyre?->serial_number,
                'new_serial' => $row->newTyre?->serial_number,
                'reason' => $row->reason,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function retreadHistory(array $filters = []): array
    {
        return TyreRetread::query()
            ->when($filters['from'] ?? null, fn ($q, string $from) => $q->whereDate('retread_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, string $to) => $q->whereDate('retread_date', '<=', $to))
            ->with('tyre')
            ->orderByDesc('retread_date')
            ->get()
            ->map(fn (TyreRetread $row) => [
                'id' => $row->id,
                'retread_date' => $row->retread_date?->toDateString(),
                'serial_number' => $row->tyre?->serial_number,
                'vendor' => $row->vendor,
                'cost' => (float) $row->cost,
                'condition_after' => $row->condition_after,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function vehicleWiseUsage(array $filters = []): array
    {
        return TyreInstallation::query()
            ->select('vehicle_id', DB::raw('COUNT(*) as installation_count'), DB::raw('SUM(running_km) as total_km'))
            ->when($filters['from'] ?? null, fn ($q, string $from) => $q->whereDate('installed_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, string $to) => $q->whereDate('installed_at', '<=', $to))
            ->groupBy('vehicle_id')
            ->with('vehicle')
            ->get()
            ->map(fn ($row) => [
                'vehicle_id' => $row->vehicle_id,
                'registration_number' => $row->vehicle?->registration_number,
                'installation_count' => (int) $row->installation_count,
                'total_km' => (float) ($row->total_km ?? 0),
            ])
            ->all();
    }

    /**
     * @return array<string, float>
     */
    public function expenseSummary(array $filters = []): array
    {
        $rows = TyreExpense::query()
            ->when($filters['from'] ?? null, fn ($q, string $from) => $q->whereDate('expense_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, string $to) => $q->whereDate('expense_date', '<=', $to))
            ->select('expense_type', DB::raw('SUM(amount) as total'))
            ->groupBy('expense_type')
            ->get();

        $summary = collect(TyreExpenseType::cases())
            ->mapWithKeys(fn (TyreExpenseType $type) => [$type->value => 0.0])
            ->all();

        foreach ($rows as $row) {
            $summary[$row->expense_type] = (float) $row->total;
        }

        return $summary;
    }
}
