<?php

namespace App\Modules\TripManagement\Support;

use App\Enums\LoadCategory;

class LoadClassifier
{
    public static function classify(float $weightTon, float $maxLoadTon): LoadClassification
    {
        if ($weightTon == 0.0) {
            return new LoadClassification(LoadCategory::Empty, 0.0);
        }

        if ($maxLoadTon <= 0.0) {
            return new LoadClassification(LoadCategory::Full, 100.0);
        }

        $percentage = ($weightTon / $maxLoadTon) * 100;

        if ($weightTon < $maxLoadTon) {
            return new LoadClassification(LoadCategory::Half, $percentage);
        }

        return new LoadClassification(LoadCategory::Full, $percentage);
    }
}
