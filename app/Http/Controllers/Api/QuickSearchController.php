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
                ->with(['vehicle', 'driver', 'customer', 'startStation', 'routeLegs.toStation'])
                ->where('trip_number', 'like', '%' . $q . '%')
                ->orWhereHas('vehicle', function($query) use ($q) {
                    $query->where('registration_number', 'like', '%' . $q . '%');
                })
                ->orderByDesc('start_at')
                ->orderByDesc('id')
                ->limit(50)
                ->get();

            // Exact match on trip_number
            $exact = Trip::query()
                ->where('trip_number', $q)
                ->first();

            if ($exact) {
                $exact->load([
                    'vehicle',
                    'driver',
                    'customer',
                    'broker',
                    'startStation',
                    'routeLegs.toStation',
                    'routeLegs.customer',
                    'pickups.station',
                    'expenses',
                    'invoices.customer',
                    'invoices.payments',
                ]);

                $detail = [
                    'trip' => $exact,
                    'expenses' => $exact->expenses,
                    'invoices' => $exact->invoices,
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
