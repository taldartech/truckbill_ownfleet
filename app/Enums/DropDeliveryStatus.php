<?php

namespace App\Enums;

enum DropDeliveryStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Delivered => 'Delivered',
        };
    }
}
