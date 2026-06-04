<?php

namespace App\Enums;

enum TyrePosition: string
{
    case FrontLeft = 'FRONT_LEFT';
    case FrontRight = 'FRONT_RIGHT';
    case RearLeftOuter = 'REAR_LEFT_OUTER';
    case RearLeftInner = 'REAR_LEFT_INNER';
    case RearRightOuter = 'REAR_RIGHT_OUTER';
    case RearRightInner = 'REAR_RIGHT_INNER';
    case Spare = 'SPARE';

    public function label(): string
    {
        return match ($this) {
            self::FrontLeft => 'Front Left',
            self::FrontRight => 'Front Right',
            self::RearLeftOuter => 'Rear Left (Outer)',
            self::RearLeftInner => 'Rear Left (Inner)',
            self::RearRightOuter => 'Rear Right (Outer)',
            self::RearRightInner => 'Rear Right (Inner)',
            self::Spare => 'Spare',
        };
    }
}
