<?php

namespace App\Enums;

enum BatteryStatus: string
{
    case InStock = 'IN_STOCK';
    case Installed = 'INSTALLED';
    case Removed = 'REMOVED';
    case UnderInspection = 'UNDER_INSPECTION';
    case Returned = 'RETURNED';
    case Scrapped = 'SCRAPPED';

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'In Stock',
            self::Installed => 'Installed',
            self::Removed => 'Removed',
            self::UnderInspection => 'Under Inspection',
            self::Returned => 'Returned',
            self::Scrapped => 'Scrapped',
        };
    }
}
