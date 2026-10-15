<?php

namespace App\Enums;

enum JournalSide: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Debit => 'Dare',
            self::Credit => 'Avere',
        };
    }

    public function opposite(): self
    {
        return $this === self::Debit ? self::Credit : self::Debit;
    }
}
