<?php

namespace App\Enums;

enum WealthBucket: string
{
    case Liquid = 'liquid';
    case Parked = 'parked';
    case Invested = 'invested';

    public function label(): string
    {
        return match ($this) {
            self::Liquid => 'Liquidità',
            self::Parked => 'Fermi',
            self::Invested => 'Investimenti',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Liquid => 'Spendibile subito: entra nella cassa e nella previsione.',
            self::Parked => 'Accantonato: resta nel patrimonio, fuori dalla cassa di tutti i giorni.',
            self::Invested => 'Liquidità del conto titoli. I titoli si registrano a parte, con le quantità.',
        };
    }

    /**
     * @return list<array{value: string, label: string, hint: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $bucket) => ['value' => $bucket->value, 'label' => $bucket->label(), 'hint' => $bucket->hint()],
            self::cases(),
        );
    }
}
