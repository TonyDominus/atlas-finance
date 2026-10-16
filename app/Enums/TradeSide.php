<?php

namespace App\Enums;

enum TradeSide: string
{
    case Buy = 'buy';
    case Sell = 'sell';

    public function label(): string
    {
        return match ($this) {
            self::Buy => 'Acquisto',
            self::Sell => 'Vendita',
        };
    }
}
