<?php

namespace Tests\Unit\Modules\TripManagement;

use App\Enums\LoadCategory;
use App\Modules\TripManagement\Support\LoadClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoadClassifierTest extends TestCase
{
    #[DataProvider('classificationProvider')]
    public function test_classifies_load_with_percentage(float $weight, float $maxLoad, LoadCategory $category, float $percentage): void
    {
        $result = LoadClassifier::classify($weight, $maxLoad);

        $this->assertSame($category, $result->category);
        $this->assertEqualsWithDelta($percentage, $result->percentage, 0.01);
    }

    /**
     * @return array<string, array{float, float, LoadCategory, float}>
     */
    public static function classificationProvider(): array
    {
        return [
            'empty' => [0, 22, LoadCategory::Empty, 0],
            'half' => [10, 22, LoadCategory::Half, 45.45],
            'full at max' => [22, 22, LoadCategory::Full, 100],
            'overload' => [30, 22, LoadCategory::Full, 136.36],
            'loaded without max load defaults to full' => [10, 0, LoadCategory::Full, 100],
            'zero weight with zero max load stays empty' => [0, 0, LoadCategory::Empty, 0],
        ];
    }
}
