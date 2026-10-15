<?php

namespace App\Enums;

enum JournalEntryStatus: string
{
    case Forecast = 'forecast';
    case Pending = 'pending';
    case Posted = 'posted';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Forecast => 'Previsto',
            self::Pending => 'In attesa',
            self::Posted => 'Contabilizzato',
            self::Void => 'Annullato',
        };
    }
}
