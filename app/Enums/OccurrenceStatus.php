<?php

namespace App\Enums;

enum OccurrenceStatus: string
{
    case Forecast = 'forecast';
    case Linked = 'linked';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Forecast => 'Prevista',
            self::Linked => 'Contabilizzata',
            self::Skipped => 'Saltata',
        };
    }
}
