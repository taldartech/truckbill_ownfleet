<?php

namespace App\Support;

use App\Trip;
use Symfony\Component\HttpFoundation\Response;

class ArchivedTripGuard
{
    public static function ensureModifiable(Trip $trip): void
    {
        if ($trip->trashed()) {
            abort(Response::HTTP_GONE, 'This trip has been archived and cannot be modified.');
        }
    }

    public static function findForShow(string $tripId): Trip
    {
        return Trip::query()->withTrashed()->findOrFail($tripId);
    }
}
