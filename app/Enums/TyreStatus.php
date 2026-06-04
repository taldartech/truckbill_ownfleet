<?php

namespace App\Enums;

enum TyreStatus: string
{
    case InStock = 'IN_STOCK';
    case Installed = 'INSTALLED';
    case Retreaded = 'RETREADED';
    case Damaged = 'DAMAGED';
    case Scrapped = 'SCRAPPED';
}
