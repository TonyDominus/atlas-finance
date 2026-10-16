<?php

declare(strict_types=1);

namespace App\Services\Investments;

final class PriceSources
{
    public const MANUAL = 'manual';

    public const ALPHA_VANTAGE = 'alphavantage';

    public static function label(string $source): string
    {
        return match ($source) {
            self::MANUAL => 'Inserito a mano',
            self::ALPHA_VANTAGE => 'Alpha Vantage',
            default => $source,
        };
    }
}
