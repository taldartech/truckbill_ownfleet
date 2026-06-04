<?php

namespace App\Enums;

enum FreightPaymentStatus: string
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Paid = 'paid';
}
