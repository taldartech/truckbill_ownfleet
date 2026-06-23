<?php

namespace App\Enums;

enum TyreStatus: string
{
    case InStock = 'IN_STOCK';
    case Installed = 'INSTALLED';
    case Scrapped = 'SCRAPPED';
}
