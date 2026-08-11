<?php

namespace App\Enums;

enum InvoiceParticularsMode: string
{
    case Separate = 'separate';
    case Combined = 'combined';

    public function label(): string
    {
        return match ($this) {
            self::Separate => 'Separate line per route',
            self::Combined => 'Combined (single line)',
        };
    }
}
