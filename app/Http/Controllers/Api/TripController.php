<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\UpdateTripRequest;
use App\Http\Resources\TripResource;
use App\Modules\DriverManagement\Services\DriverAssignmentService;
use App\Modules\TripManagement\Services\TripService;
use App\Modules\TripManagement\Services\TripValidationService;
use App\Trip;
use App\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TripController extends Controller
{
    public function __construct(
        private readonly TripService $tripService,
        private readonly TripValidationService $tripValidation,
        private readonly DriverAssignmentService $driverAssignmentService,
    ) {}

    public function nextTripNumber(): JsonResponse
    {
        $this->authorize('create', Trip::class);

        return response()->json([
            'trip_number' => $this->tripService->previewNextTripNumber(),
        ]);
    }

    public function vehiclePrimaryDriver(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);

        $asOfDate = $request->filled('as_of_date')
            ? $request->string('as_of_date')->toString()
            : null;

        $summary = $this->driverAssignmentService->primaryAssignmentSummary($vehicle->id, $asOfDate);
        $assignment = $summary['assignment'] ?? null;

        return response()->json([
            'vehicle_id' => $vehicle->id,
            'primary_driver_id' => $assignment['driver_id'] ?? null,
            'driver' => $assignment['driver'] ?? null,
            'has_assignment' => (bool) ($summary['has_active_primary'] ?? false),
            'as_of_date' => $summary['as_of_date'] ?? null,
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Trip::class);

        $perPage = min(max((int) $request->input('per_page', 15), 1), 500);

        $filters = $request->only([
            'vehicle_id',
            'driver_id',
            'status',
            'from_date',
            'to_date',
            'customer_id',
            'broker_id',
            'customer_name',
            'trip_number',
            'vehicle_search',
            'driver_search',
        ]);

        return TripResource::collection($this->tripService->paginate($perPage, $filters));
    }

    public function store(StoreTripRequest $request): JsonResponse
    {
        $trip = $this->tripService->create(
            $request->validated(),
            $request->user()?->id,
        );

        return (new TripResource($trip))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Trip $trip): TripResource
    {
        $this->authorize('view', $trip);

        $trip->load(['vehicle', 'driver', 'routeLegs', 'expenses.routeLeg']);

        return new TripResource($trip);
    }

    public function update(UpdateTripRequest $request, Trip $trip): TripResource
    {
        $trip = $this->tripService->update(
            $trip,
            $request->validated(),
            $request->user()?->id,
        );

        return new TripResource($trip);
    }

    public function destroy(Trip $trip): Response
    {
        $this->authorize('delete', $trip);
        $this->tripService->delete($trip);

        return response()->noContent();
    }
}
