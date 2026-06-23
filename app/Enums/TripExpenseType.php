<?php

namespace App\Enums;

enum TripExpenseType: string
{
    case Diesel = 'DIESEL';
    case Toll = 'TOLL';
    case DriverExpense = 'DRIVER_EXPENSE';
    case LoadingCharges = 'LOADING_CHARGES';
    case UnloadingCharges = 'UNLOADING_CHARGES';
    case Repair = 'REPAIR';
    case FoodExpense = 'FOOD_EXPENSE';
    case Parking = 'PARKING';
    case RtoExpense = 'RTO_EXPENSE';
    case PermitBorderTax = 'PERMIT_BORDER_TAX';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Diesel => 'Diesel',
            self::Toll => 'Toll',
            self::DriverExpense => 'Driver Expense',
            self::LoadingCharges => 'Loading Charges',
            self::UnloadingCharges => 'Unloading Charges',
            self::Repair => 'Repair',
            self::FoodExpense => 'Food Expense',
            self::Parking => 'Parking',
            self::RtoExpense => 'RTO',
            self::PermitBorderTax => 'Permit / border tax',
            self::Other => 'Other',
        };
    }
}
