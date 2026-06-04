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
    case Other = 'OTHER';
}
