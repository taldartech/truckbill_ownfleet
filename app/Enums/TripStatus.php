<?php

namespace App\Enums;

enum TripStatus: string
{
    case Created = 'CREATED';
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
}
