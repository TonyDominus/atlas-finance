<?php

namespace App\Enums;

enum LoanDirection: string
{
    case Given = 'given';
    case Received = 'received';

    public function label(): string
    {
        return match ($this) {
            self::Given => 'Prestito concesso',
            self::Received => 'Prestito ricevuto',
        };
    }
}
