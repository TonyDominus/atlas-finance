<?php

namespace App\Enums;

enum LoanRepaymentKind: string
{
    case Cash = 'cash';
    case Compensation = 'compensation';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Denaro',
            self::Compensation => 'Compensazione',
        };
    }
}
