<?php

namespace App\Enums;

enum BatteryType: string
{
    case LeadAcid = 'LEAD_ACID';
    case MaintenanceFree = 'MAINTENANCE_FREE_SMF';
    case Tubular = 'TUBULAR';
    case LithiumIon = 'LITHIUM_ION';
    case Gel = 'GEL';

    public function label(): string
    {
        return match ($this) {
            self::LeadAcid => 'Lead Acid (Flooded)',
            self::MaintenanceFree => 'Maintenance Free (SMF)',
            self::Tubular => 'Tubular',
            self::LithiumIon => 'Lithium-Ion',
            self::Gel => 'Gel Battery',
        };
    }
}
