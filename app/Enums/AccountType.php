<?php

namespace App\Enums;

enum AccountType: string
{
    case Checking = 'checking';
    case Savings = 'savings';
    case Card = 'card';
    case Investment = 'investment';
    case Cash = 'cash';
    case Wallet = 'wallet';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Checking => 'Conto corrente',
            self::Savings => 'Risparmio',
            self::Card => 'Carta',
            self::Investment => 'Investimento',
            self::Cash => 'Contanti',
            self::Wallet => 'Wallet',
            self::Other => 'Altro',
        };
    }

    public function defaultBucket(): WealthBucket
    {
        return match ($this) {
            self::Checking, self::Card, self::Cash, self::Wallet => WealthBucket::Liquid,
            self::Savings, self::Other => WealthBucket::Parked,
            self::Investment => WealthBucket::Invested,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type) => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
