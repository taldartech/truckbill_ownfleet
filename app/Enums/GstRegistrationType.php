<?php

namespace App\Enums;

enum GstRegistrationType: string
{
    case Regular = 'regular';
    case Composition = 'composition';
    case Unregistered = 'unregistered';

    public function showsGstFields(): bool
    {
        return $this === self::Regular || $this === self::Composition;
    }

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Regular',
            self::Composition => 'Composition',
            self::Unregistered => 'Unregistered',
        };
    }
}
