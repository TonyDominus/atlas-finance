<?php

namespace App\Enums;

enum CounterpartyType: string
{
    case Person = 'person';
    case Merchant = 'merchant';
    case Institution = 'institution';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Person => 'Persona',
            self::Merchant => 'Esercente',
            self::Institution => 'Istituto',
            self::Other => 'Altro',
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
