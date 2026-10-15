<?php

namespace App\Enums;

enum RecurrenceKind: string
{
    case Expense = 'expense';
    case Income = 'income';
    case Transfer = 'transfer';
    case Fee = 'fee';

    public function label(): string
    {
        return match ($this) {
            self::Expense => 'Spesa',
            self::Income => 'Entrata',
            self::Transfer => 'Trasferimento',
            self::Fee => 'Canone o commissione',
        };
    }

    public function isOutflow(): bool
    {
        return $this === self::Expense || $this === self::Fee;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $kind) => ['value' => $kind->value, 'label' => $kind->label()],
            self::cases(),
        );
    }
}
