<?php

namespace App\Enums;

enum TripStatus: string
{
    case Pending = 'PENDING';
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
    case Invoiced = 'INVOICED';
}
