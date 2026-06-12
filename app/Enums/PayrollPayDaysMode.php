<?php

namespace App\Enums;

enum PayrollPayDaysMode: string
{
    case Calendar = 'calendar';
    case Fixed30 = 'fixed_30';
}
