<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ArchiveTripRequest;
use App\Http\Requests\CheckTripWindowRequest;
use App\Http\Requests\DeleteTripRequest;
use App\Http\Requests\RestoreTripRequest;
use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\TripBiltyPdfRequest;
use App\Http\Requests\UpdateTripRequest;
use App\Http\Resources\TripResource;
use App\Modules\DriverManagement\Services\DriverAssignmentService;
use App\Modules\TripManagement\Services\TripBiltyService;
use App\Modules\TripManagement\Services\TripService;
use App\Modules\TripManagement\Services\TripValidationService;
use App\Support\ArchivedTripGuard;
use App\Support\TripArchiveImpact;
use App\Trip;
use App\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TripController extends Controller
{
    public function __construct(
        private readonly TripService $tripService,
        private readonly TripValidationService $tripValidation,
        private readonly DriverAssignmentService $driverAssignmentService,
        private readonly TripBiltyService $tripBiltyService,
    ) {}

    public function nextTripNumber(): JsonResponse
    {
        $this->authorize('create', Trip::class);

        return response()->json([
            'trip_number' => $this->tripService->previewNextTripNumber(),
        ]);
    }

    public function checkTripWindow(CheckTripWindowRequest $request): JsonResponse
    {
        $data = $request->validated();
        $ignoreTripId = $data['ignore_trip_id'] ?? null;

        $vehicleOverlap = $this->tripValidation->findOverlappingOpenTrip(
            vehicleId: $data['vehicle_id'],
            startAt: $data['start_at'],
            endAt: $data['end_at'] ?? null,
            ignoreTripId: $ignoreTripId,
        );

        if ($vehicleOverlap !== null) {
            return response()->json([
                'available' => false,
                'conflict_type' => 'vehicle',
                'trip_id' => $vehicleOverlap->id,
                'trip_number' => $vehicleOverlap->trip_number,
                'messages' => [
                    'start_at' => ['This truck already has an open trip in the selected date range. Complete or cancel it first.'],
                    'vehicle_id' => ["Trip {$vehicleOverlap->trip_number} is already scheduled for this truck on overlapping dates."],
                ],
            ]);
        }

        if (! empty($data['driver_id'])) {
            $driverOverlap = $this->tripValidation->findOverlappingOpenTrip(
                driverId: $data['driver_id'],
                startAt: $data['start_at'],
                endAt: $data['end_at'] ?? null,
                ignoreTripId: $ignoreTripId,
            );

            if ($driverOverlap !== null) {
                return response()->json([
                    'available' => false,
                    'conflict_type' => 'driver',
                    'trip_id' => $driverOverlap->id,
                    'trip_number' => $driverOverlap->trip_number,
                    'messages' => [
                        'start_at' => ['This driver already has an open trip in the selected date range. Complete or cancel it first.'],
                        'driver_id' => ["Trip {$driverOverlap->trip_number} is already scheduled for this driver on overlapping dates."],
                    ],
                ]);
            }
        }

        return response()->json([
            'available' => true,
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
            'trip_type',
            'from_date',
            'to_date',
            'customer_id',
            'broker_id',
            'customer_name',
            'search',
            'trip_number',
            'vehicle_search',
            'driver_search',
            'archived',
        ]);

        if (($filters['archived'] ?? null) === 'archived') {
            $this->authorize('viewArchived', Trip::class);
        }

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

    public function show(string $trip): TripResource
    {
        $trip = ArchivedTripGuard::findForShow($trip);

        $this->authorize('view', $trip);

        $trip->load([
            'vehicle',
            'driver',
            'substituteDriver',
            'startStation.city',
            'routeLegs.customer',
            'expenses.routeLeg',
            'statusHistories' => fn ($query) => $query->latest('changed_at'),
        ]);

        return new TripResource($trip);
    }

    public function update(UpdateTripRequest $request, Trip $trip): TripResource
    {
        ArchivedTripGuard::ensureModifiable($trip);

        $trip = $this->tripService->update(
            $trip,
            $request->validated(),
            $request->user(),
        );

        return new TripResource($trip);
    }

    public function archiveImpact(Trip $trip): JsonResponse
    {
        ArchivedTripGuard::ensureModifiable($trip);

        $this->authorize('view', $trip);

        return response()->json(TripArchiveImpact::forTrip($trip, request()->user()));
    }

    /**
     * @deprecated Use archiveImpact()
     */
    public function deletionImpact(Trip $trip): JsonResponse
    {
        return $this->archiveImpact($trip);
    }

    public function archive(ArchiveTripRequest $request, Trip $trip): Response
    {
        ArchivedTripGuard::ensureModifiable($trip);

        $this->tripService->archive(
            $trip,
            $request->validated('archive_reason'),
            $request->user(),
        );

        return response()->noContent();
    }

    public function restore(RestoreTripRequest $request, Trip $trip): Response
    {
        $this->tripService->restore($trip, $request->user());

        return response()->noContent();
    }

    public function biltyPdf(TripBiltyPdfRequest $request, Trip $trip): SymfonyResponse
    {
        $this->authorize('view', $trip);

        $filename = str_replace(['/', '\\'], '-', $trip->trip_number ?? $trip->id).'-bilty.pdf';

        return $this->tripBiltyService
            ->pdf($trip, $request->showFreight())
            ->stream($filename);
    }

    public function destroy(DeleteTripRequest $request, Trip $trip): Response
    {
        $this->tripService->archive(
            $trip,
            $request->validated('deletion_reason'),
            $request->user(),
        );

        return response()->noContent();
    }
}
