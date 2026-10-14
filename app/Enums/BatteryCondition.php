<?php

namespace App\Enums;

enum BatteryCondition: string
{
    case Good = 'GOOD';
    case Fair = 'FAIR';
    case Weak = 'WEAK';
    case Failed = 'FAILED';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Good',
            self::Fair => 'Fair / Monitor',
            self::Weak => 'Weak / Needs Attention',
            self::Failed => 'Failed / Replace',
        };
    }
}
