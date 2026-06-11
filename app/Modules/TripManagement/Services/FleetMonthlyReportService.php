<?php

namespace App\Modules\TripManagement\Services;

use App\Challan;
use App\DriverAssignment;
use App\Enums\TripExpenseType;
use App\Enums\TripStatus;
use App\Support\CurrentTenant;
use App\Tenant;
use App\Trip;
use App\TripExpense;
use App\TyreExpense;
use App\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FleetMonthlyReportService
{
    /**
     * @return array{
     *     month: string,
     *     company_name: string,
     *     rows: list<array<string, mixed>>,
     *     summary: array<string, float>
     * }
     */
    public function build(string $month): array
    {
        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $asOfDate = $monthEnd->toDateString();

        $tripStats = $this->tripStatsByVehicle($monthStart, $monthEnd);
        $dieselLitersByVehicle = $this->dieselLitersByVehicle($monthStart, $monthEnd);
        $tyreExpenseByVehicle = $this->tyreExpenseByVehicle($monthStart, $monthEnd);
        $driverByVehicle = $this->primaryDriversByVehicle($asOfDate);

        $rows = [];
        $serial = 0;

        $totals = [
            'total_bill' => 0.0,
            'total_saving' => 0.0,
        ];

        Vehicle::query()
            ->orderBy('registration_number')
            ->get()
            ->each(function (Vehicle $vehicle) use (
                &$rows,
                &$serial,
                &$totals,
                $tripStats,
                $dieselLitersByVehicle,
                $tyreExpenseByVehicle,
                $driverByVehicle,
            ): void {
                $serial++;
                $stats = $tripStats->get($vehicle->id);
                $tripCount = (int) ($stats->trip_count ?? 0);
                $totalKm = (float) ($stats->total_km ?? 0);
                $totalFreight = (float) ($stats->total_freight ?? 0);
                $totalExpense = (float) ($stats->total_expense ?? 0);
                $dieselLiters = (float) ($dieselLitersByVehicle->get($vehicle->id)?->diesel_liters ?? 0);
                $tyreExpense = (float) ($tyreExpenseByVehicle->get($vehicle->id)?->tyre_expense ?? 0);
                $saving = round($totalFreight - $totalExpense, 2);
                $average = $dieselLiters > 0 ? round($totalKm / $dieselLiters, 2) : 0.0;

                $totals['total_bill'] += $totalFreight;
                $totals['total_saving'] += $saving;

                $rows[] = [
                    's_no' => $serial,
                    'registration_number' => $vehicle->registration_number,
                    'driver_name' => $driverByVehicle->get($vehicle->id)?->driver?->name,
                    'trip_count' => $tripCount,
                    'total_km' => round($totalKm, 2),
                    'diesel_liters' => round($dieselLiters, 2),
                    'average_km_per_liter' => $average,
                    'total_expense' => round($totalExpense, 2),
                    'tyre_expense' => round($tyreExpense, 2),
                    'saving' => $saving,
                ];
            });

        $rtoEntry = $this->rtoExpenseTotal($monthStart, $monthEnd);
        $challanRto = $this->challanTotal($monthStart, $monthEnd);
        $tyreBill = $this->tyreBillTotal($monthStart, $monthEnd);
        $totalSaving = round($totals['total_saving'], 2);
        $netSaving = round($totalSaving - $tyreBill, 2);

        return [
            'month' => $month,
            'company_name' => $this->companyName(),
            'rows' => $rows,
            'summary' => [
                'total_bill' => round($totals['total_bill'], 2),
                'rto_entry' => round($rtoEntry, 2),
                'challan_rto' => round($challanRto, 2),
                'total_saving' => $totalSaving,
                'tyre_bill' => round($tyreBill, 2),
                'net_saving' => $netSaving,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportRows(string $month): array
    {
        $report = $this->build($month);
        $exportRows = $report['rows'];

        $exportRows[] = [
            's_no' => '',
            'registration_number' => '',
            'driver_name' => '',
            'trip_count' => '',
            'total_km' => '',
            'diesel_liters' => '',
            'average_km_per_liter' => '',
            'total_expense' => '',
            'tyre_expense' => '',
            'saving' => '',
        ];

        $summary = $report['summary'];

        $exportRows[] = [
            's_no' => 'Summary',
            'registration_number' => 'Total Bill',
            'driver_name' => $summary['total_bill'],
            'trip_count' => 'RTO Entry',
            'total_km' => $summary['rto_entry'],
            'diesel_liters' => 'Challan RTO',
            'average_km_per_liter' => $summary['challan_rto'],
            'total_expense' => 'Total Saving',
            'tyre_expense' => $summary['total_saving'],
            'saving' => 'Tyre Bill',
        ];

        $exportRows[] = [
            's_no' => '',
            'registration_number' => '',
            'driver_name' => '',
            'trip_count' => '',
            'total_km' => '',
            'diesel_liters' => '',
            'average_km_per_liter' => '',
            'total_expense' => $summary['tyre_bill'],
            'tyre_expense' => 'Net Saving',
            'saving' => $summary['net_saving'],
        ];

        return $exportRows;
    }

    private function companyName(): string
    {
        $tenant = Tenant::query()->find(CurrentTenant::requireId());

        return (string) ($tenant?->name ?? '');
    }

    /**
     * @return \Illuminate\Support\Collection<string, object{
     *     vehicle_id: string,
     *     trip_count: int|string,
     *     total_km: float|string,
     *     total_freight: float|string,
     *     total_expense: float|string
     * }>
     */
    private function tripStatsByVehicle(Carbon $monthStart, Carbon $monthEnd): \Illuminate\Support\Collection
    {
        return Trip::query()
            ->where('status', '!=', TripStatus::Cancelled->value)
            ->whereDate('start_at', '>=', $monthStart->toDateString())
            ->whereDate('start_at', '<=', $monthEnd->toDateString())
            ->groupBy('vehicle_id')
            ->select([
                'vehicle_id',
                DB::raw('COUNT(*) as trip_count'),
                DB::raw('COALESCE(SUM(total_distance_km), 0) as total_km'),
                DB::raw('COALESCE(SUM(total_freight_amount), 0) as total_freight'),
                DB::raw('COALESCE(SUM(total_expense_amount), 0) as total_expense'),
            ])
            ->get()
            ->keyBy('vehicle_id');
    }

    /**
     * @return \Illuminate\Support\Collection<string, object{vehicle_id: string, diesel_liters: float|string}>
     */
    private function dieselLitersByVehicle(Carbon $monthStart, Carbon $monthEnd): \Illuminate\Support\Collection
    {
        return TripExpense::query()
            ->join('trips', 'trips.id', '=', 'trip_expenses.trip_id')
            ->where('trip_expenses.expense_type', TripExpenseType::Diesel->value)
            ->where('trips.status', '!=', TripStatus::Cancelled->value)
            ->whereDate('trips.start_at', '>=', $monthStart->toDateString())
            ->whereDate('trips.start_at', '<=', $monthEnd->toDateString())
            ->groupBy('trips.vehicle_id')
            ->select([
                'trips.vehicle_id',
                DB::raw('COALESCE(SUM(trip_expenses.fuel_liters), 0) as diesel_liters'),
            ])
            ->get()
            ->keyBy('vehicle_id');
    }

    /**
     * @return \Illuminate\Support\Collection<string, object{vehicle_id: string, tyre_expense: float|string}>
     */
    private function tyreExpenseByVehicle(Carbon $monthStart, Carbon $monthEnd): \Illuminate\Support\Collection
    {
        return TyreExpense::query()
            ->join('tyre_installations', function ($join): void {
                $join->on('tyre_installations.tyre_id', '=', 'tyre_expenses.tyre_id')
                    ->whereColumn('tyre_installations.installed_at', '<=', 'tyre_expenses.expense_date')
                    ->where(function ($query): void {
                        $query->whereNull('tyre_installations.removed_at')
                            ->orWhereColumn('tyre_installations.removed_at', '>', 'tyre_expenses.expense_date');
                    });
            })
            ->whereDate('tyre_expenses.expense_date', '>=', $monthStart->toDateString())
            ->whereDate('tyre_expenses.expense_date', '<=', $monthEnd->toDateString())
            ->groupBy('tyre_installations.vehicle_id')
            ->select([
                'tyre_installations.vehicle_id',
                DB::raw('COALESCE(SUM(tyre_expenses.amount), 0) as tyre_expense'),
            ])
            ->get()
            ->keyBy('vehicle_id');
    }

    /**
     * @return \Illuminate\Support\Collection<string, DriverAssignment>
     */
    private function primaryDriversByVehicle(string $asOfDate): \Illuminate\Support\Collection
    {
        return DriverAssignment::query()
            ->activePrimaryOn($asOfDate)
            ->with('driver')
            ->get()
            ->keyBy('vehicle_id');
    }

    private function rtoExpenseTotal(Carbon $monthStart, Carbon $monthEnd): float
    {
        return (float) TripExpense::query()
            ->join('trips', 'trips.id', '=', 'trip_expenses.trip_id')
            ->where('trip_expenses.expense_type', TripExpenseType::RtoExpense->value)
            ->where('trips.status', '!=', TripStatus::Cancelled->value)
            ->whereDate('trips.start_at', '>=', $monthStart->toDateString())
            ->whereDate('trips.start_at', '<=', $monthEnd->toDateString())
            ->sum('trip_expenses.amount');
    }

    private function challanTotal(Carbon $monthStart, Carbon $monthEnd): float
    {
        return (float) Challan::query()
            ->whereDate('challan_date', '>=', $monthStart->toDateString())
            ->whereDate('challan_date', '<=', $monthEnd->toDateString())
            ->sum('amount');
    }

    private function tyreBillTotal(Carbon $monthStart, Carbon $monthEnd): float
    {
        return (float) TyreExpense::query()
            ->whereDate('expense_date', '>=', $monthStart->toDateString())
            ->whereDate('expense_date', '<=', $monthEnd->toDateString())
            ->sum('amount');
    }
}
