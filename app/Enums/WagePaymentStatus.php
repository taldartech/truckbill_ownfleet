<?php

namespace App\Enums;

enum WagePaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
}
