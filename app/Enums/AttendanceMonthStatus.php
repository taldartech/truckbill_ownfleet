<?php

namespace App\Enums;

enum AttendanceMonthStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Paid = 'paid';
}
