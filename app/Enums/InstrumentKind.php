<?php

namespace App\Enums;

enum InstrumentKind: string
{
    case Stock = 'stock';
    case Etf = 'etf';
    case Fund = 'fund';
    case Bond = 'bond';
    case Crypto = 'crypto';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Stock => 'Azione',
            self::Etf => 'ETF',
            self::Fund => 'Fondo',
            self::Bond => 'Obbligazione',
            self::Crypto => 'Cripto',
            self::Other => 'Altro',
        };
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
