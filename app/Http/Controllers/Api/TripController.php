<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\UpdateTripRequest;
use App\Http\Resources\TripResource;
use App\Trip;
use App\TripRouteLeg;
use App\TripStatusHistory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class TripController
{
    public function index(): AnonymousResourceCollection
    {
        return TripResource::collection(Trip::query()->latest()->paginate());
    }

    public function store(StoreTripRequest $request): TripResource
    {
        $trip = DB::transaction(function () use ($request): Trip {
            $trip = Trip::query()->create([
                ...$request->safe()->except(['route_legs']),
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]);

            $routeLegs = $request->validated('route_legs', []);
            foreach ($routeLegs as $routeLeg) {
                TripRouteLeg::query()->create([
                    ...$routeLeg,
                    'trip_id' => $trip->id,
                ]);
            }

            TripStatusHistory::query()->create([
                'trip_id' => $trip->id,
                'to_status' => $trip->status,
                'changed_at' => now(),
                'changed_by' => $request->user()?->id,
            ]);

            return $trip;
        });

        return new TripResource($trip->fresh());
    }

    /**
     * Display the specified resource.
     */
    public function show(Trip $trip): TripResource
    {
        return new TripResource($trip->load('routeLegs'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTripRequest $request, Trip $trip): TripResource
    {
        $oldStatus = $trip->status;
        $trip->update([
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ]);

        if ($request->filled('status') && $oldStatus !== $trip->status) {
            TripStatusHistory::query()->create([
                'trip_id' => $trip->id,
                'from_status' => $oldStatus,
                'to_status' => $trip->status,
                'changed_at' => now(),
                'changed_by' => $request->user()?->id,
            ]);
        }

        return new TripResource($trip->fresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Trip $trip): Response
    {
        $trip->delete();

        return response()->noContent();
    }
}
