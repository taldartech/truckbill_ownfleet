<?php

namespace App\Enums;

enum FreightBillingStatus: string
{
    case Unbilled = 'unbilled';
    case Billed = 'billed';
    case Invoiced = 'invoiced';
}
