<?php

namespace App\Enums;

enum JournalOrigin: string
{
    case Manual = 'manual';
    case Telegram = 'telegram';
    case Csv = 'csv';
    case Api = 'api';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manuale',
            self::Telegram => 'Telegram',
            self::Csv => 'CSV',
            self::Api => 'API',
        };
    }
}
