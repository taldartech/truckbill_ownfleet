<?php

namespace App\Enums;

enum ChallanPaymentStatus: string
{
    case Paid = 'PAID';
    case Unpaid = 'UNPAID';
}
