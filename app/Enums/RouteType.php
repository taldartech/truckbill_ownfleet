<?php

namespace App\Enums;

enum RouteType: string
{
    case Loaded = 'LOADED';
    case HalfLoaded = 'HALF_LOADED';
    case Empty = 'EMPTY';
}
