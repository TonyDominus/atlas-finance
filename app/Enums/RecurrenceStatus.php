<?php

namespace App\Enums;

enum RecurrenceStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Attiva',
            self::Paused => 'Sospesa',
            self::Ended => 'Terminata',
        };
    }
}
