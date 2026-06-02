<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceMonthOverviewResource extends JsonResource
{
    /**
     * @param  array{summary: array<string, int>, rows: list<array<string, mixed>>}  $resource
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{summary: array<string, int>, rows: list<array<string, mixed>>} $payload */
        $payload = $this->resource;

        return [
            'summary' => $payload['summary'],
            'rows' => $payload['rows'],
        ];
    }
}
