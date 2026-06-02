<?php

namespace App\Enums;

enum VehicleDocumentType: string
{
    case FitnessCertificate = 'fitness_certificate';
    case Puc = 'puc';
    case Insurance = 'insurance';
    case Permit = 'permit';
    case Rc = 'rc';
    case RoadTax = 'road_tax';
    case NationalPermit = 'national_permit';
    case GpsSubscription = 'gps_subscription';
    case Fastag = 'fastag';

    public function label(): string
    {
        return match ($this) {
            self::FitnessCertificate => 'Fitness Certificate',
            self::Puc => 'PUC',
            self::Insurance => 'Insurance',
            self::Permit => 'Permit',
            self::Rc => 'RC',
            self::RoadTax => 'Road Tax',
            self::NationalPermit => 'National Permit',
            self::GpsSubscription => 'GPS Subscription',
            self::Fastag => 'FASTag',
        };
    }
}
