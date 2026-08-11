<?php

namespace App\Enums;

enum PickupDeliveryStatus: string
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Partial => 'Partial',
            self::Delivered => 'Delivered',
        };
    }
}
