<?php

namespace App\Enums;

enum CategoryKind: string
{
    case Expense = 'expense';
    case Income = 'income';

    public function label(): string
    {
        return match ($this) {
            self::Expense => 'Spesa',
            self::Income => 'Entrata',
        };
    }
}
