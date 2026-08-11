<?php

namespace App\Enums;

enum TripType: string
{
    case OneWay = 'ONE_WAY';
    case RoundTrip = 'ROUND_TRIP';
    case MultiPoint = 'MULTI_POINT';
}
