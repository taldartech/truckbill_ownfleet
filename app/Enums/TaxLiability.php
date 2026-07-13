<?php

namespace App\Enums;

enum TaxLiability: string
{
    case ForwardCharge = 'forward_charge';
    case ReverseCharge = 'reverse_charge';

    public function label(): string
    {
        return match ($this) {
            self::ForwardCharge => 'Forward Charge',
            self::ReverseCharge => 'Reverse Charge (RCM)',
        };
    }

    public static function fromLegacy(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($value) {
            'forward charge', 'forward_charge' => self::ForwardCharge,
            'reverse charge', 'reverse_charge' => self::ReverseCharge,
            default => self::tryFrom($value),
        };
    }
}
