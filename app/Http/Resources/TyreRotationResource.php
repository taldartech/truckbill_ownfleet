<?php

namespace App\Http\Resources;

use App\Enums\TyrePosition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TyreRotationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_registration' => $this->vehicle?->registration_number,
            'rotation_date' => $this->rotation_date?->toDateString(),
            'previous_position' => $this->previous_position instanceof TyrePosition
                ? $this->previous_position->value
                : $this->previous_position,
            'new_position' => $this->new_position instanceof TyrePosition
                ? $this->new_position->value
                : $this->new_position,
            'reason' => $this->reason,
            'km_at_rotation' => $this->km_at_rotation !== null ? (float) $this->km_at_rotation : null,
        ];
    }
}
