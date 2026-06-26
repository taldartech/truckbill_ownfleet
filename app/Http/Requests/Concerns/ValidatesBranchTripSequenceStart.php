<?php

namespace App\Http\Requests\Concerns;

use App\Branch;
use App\Trip;
use Illuminate\Validation\Validator;

trait ValidatesBranchTripSequenceStart
{
    /**
     * @return array<int, string>
     */
    protected function tripSequenceStartRules(): array
    {
        return ['sometimes', 'integer', 'min:1', 'max:999999999'];
    }

    protected function validateBranchTripSequenceStartChange(Validator $validator, Branch $branch): void
    {
        if (! $this->has('trip_sequence_start')) {
            return;
        }

        $requestedStart = (int) $this->input('trip_sequence_start');

        if ($requestedStart === (int) $branch->trip_sequence_start) {
            return;
        }

        $hasTrips = Trip::query()
            ->withoutGlobalScopes()
            ->where('branch_id', $branch->id)
            ->exists();

        if ($hasTrips) {
            $validator->errors()->add(
                'trip_sequence_start',
                'Trip starting number cannot be changed after trips have been created for this branch.',
            );
        }
    }
}
