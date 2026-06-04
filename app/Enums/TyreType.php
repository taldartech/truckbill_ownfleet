<?php

namespace App\Enums;

enum TyreType: string
{
    case Radial = 'RADIAL';
    case Bias = 'BIAS';
    case Tubeless = 'TUBELESS';
    case Tubed = 'TUBED';
    case Other = 'OTHER';
}
