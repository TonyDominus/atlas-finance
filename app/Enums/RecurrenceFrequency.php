<?php

namespace App\Enums;

enum RecurrenceFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Semiannual = 'semiannual';
    case Yearly = 'yearly';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Giornaliera',
            self::Weekly => 'Settimanale',
            self::Monthly => 'Mensile',
            self::Quarterly => 'Trimestrale',
            self::Semiannual => 'Semestrale',
            self::Yearly => 'Annuale',
            self::Custom => 'Personalizzata',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $frequency) => ['value' => $frequency->value, 'label' => $frequency->label()],
            self::cases(),
        );
    }
}
