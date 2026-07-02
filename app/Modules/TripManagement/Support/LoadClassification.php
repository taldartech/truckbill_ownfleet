<?php

namespace App\Modules\TripManagement\Support;

use App\Enums\LoadCategory;

readonly class LoadClassification
{
    public function __construct(
        public LoadCategory $category,
        public float $percentage,
    ) {}

    /**
     * @return array{category: string, percentage: float}
     */
    public function toArray(): array
    {
        return [
            'category' => $this->category->value,
            'percentage' => round($this->percentage, 2),
        ];
    }
}
