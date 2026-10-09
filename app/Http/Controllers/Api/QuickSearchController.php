<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Trip;
use Illuminate\Http\Request;

class QuickSearchController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $q = trim((string) ($validated['q'] ?? ''));

        $trips = collect();
        $detail = null;

        if ($q !== '') {
            $trips = Trip::query()
                ->with(['vehicle', 'driver', 'substituteDriver', 'customer', 'startStation', 'routeLegs.fromStation', 'routeLegs.toStation'])
                ->where(function ($query) use ($q) {
                    $query->where('trip_number', 'like', '%' . $q . '%')
                        ->orWhereHas('expenses', function ($sub) use ($q) {
                            $sub->where('notes', 'like', '%' . $q . '%')
                                ->orWhere('other_type', 'like', '%' . $q . '%')
                                ->orWhere('expense_type', 'like', '%' . $q . '%');
                        });
                })
                ->orderByDesc('start_at')
                ->orderByDesc('id')
                ->limit(50)
                ->get();

            $exact = Trip::query()
                ->where('trip_number', $q)
                ->first();

            if (!$exact && $trips->count() === 1) {
                $exact = $trips->first();
            }

            if ($exact) {
                $exact->load([
                    'vehicle',
                    'driver',
                    'substituteDriver',
                    'customer',
                    'broker',
                    'startStation.city',
                    'routeLegs.fromStation.city',
                    'routeLegs.toStation.city',
                    'routeLegs.customer',
                    'routeLegs.pickup.station',
                    'pickups.station.city',
                    'pickups.drops.fromStation.city',
                    'pickups.drops.toStation.city',
                    'pickups.drops.customer',
                    'expenses.routeLeg.fromStation',
                    'expenses.routeLeg.toStation',
                    'invoices.customer',
                    'invoices.payments',
                ]);

                $detail = [
                    'trip' => $exact,
                    'expenses' => $exact->expenses,
                    'invoices' => $exact->invoices,
                    'diesel_performance' => \App\Modules\TripManagement\Support\TripDieselPerformance::forTrip($exact),
                    'profit_amount' => $exact->profitAmount(),
                ];
            }
        }

        return response()->json([
            'trips' => $trips,
            'query' => $q,
            'detail' => $detail,
        ]);
    }
}
