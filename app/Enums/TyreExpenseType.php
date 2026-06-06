<?php

namespace App\Enums;

enum TyreExpenseType: string
{
    case Purchase = 'PURCHASE';
    case Repair = 'REPAIR';
    case Retread = 'RETREAD';
    case Rotation = 'ROTATION';
    case Maintenance = 'MAINTENANCE';
    case Replacement = 'REPLACEMENT';
}
