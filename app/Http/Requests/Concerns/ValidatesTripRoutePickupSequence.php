<?php

namespace App\Http\Requests\Concerns;

use Carbon\Carbon;
use Illuminate\Validation\Validator;

trait ValidatesTripRoutePickupSequence
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateRoutePickupSequence($validator);
        });
    }

    protected function validateRoutePickupSequence(Validator $validator): void
    {
        $legs = $this->input('route_legs');

        if (! is_array($legs)) {
            return;
        }

        $previousPickupDate = null;

        foreach ($legs as $index => $leg) {
            if (! is_array($leg)) {
                continue;
            }

            $pickupRaw = $leg['pickup_date'] ?? null;

            if ($pickupRaw === null || $pickupRaw === '') {
                continue;
            }

            try {
                $pickupDate = Carbon::parse((string) $pickupRaw)->toDateString();
            } catch (\Throwable) {
                continue;
            }

            if ($previousPickupDate !== null && $pickupDate < $previousPickupDate) {
                $validator->errors()->add(
                    "route_legs.{$index}.pickup_date",
                    'Pickup date cannot be before the previous stop pickup date.',
                );
            }

            $previousPickupDate = $pickupDate;
        }
    }
}
