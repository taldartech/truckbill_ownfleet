<?php

namespace App\Enums;

enum TyreDamageType: string
{
    case Puncture = 'PUNCTURE';
    case Burst = 'BURST';
    case Cut = 'CUT';
    case Sidewall = 'SIDEWALL';
    case Other = 'OTHER';
}
