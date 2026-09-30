<?php

namespace App\Enums;

enum BatteryRemovalReason: string
{
    case BatteryFailed = 'BATTERY_FAILED';
    case LowVoltage = 'LOW_VOLTAGE';
    case ScheduledAging = 'SCHEDULED_AGING';
    case PhysicalDamage = 'PHYSICAL_DAMAGE';
    case WarrantyClaim = 'WARRANTY_CLAIM';
    case Transferred = 'TRANSFERRED';
    case VehicleMaintenance = 'VEHICLE_MAINTENANCE';

    public function label(): string
    {
        return match ($this) {
            self::BatteryFailed => 'Battery Failed',
            self::LowVoltage => 'Low Voltage / Not Charging',
            self::ScheduledAging => 'Aging / Scheduled Replacement',
            self::PhysicalDamage => 'Physical Damage / Terminal Leak',
            self::WarrantyClaim => 'Warranty Claim / Dealer Return',
            self::Transferred => 'Transferred to Other Truck',
            self::VehicleMaintenance => 'Removed for Vehicle Maintenance',
        };
    }
}
