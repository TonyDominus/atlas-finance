<?php

declare(strict_types=1);

namespace App\Domain\Money;

final class Currency
{
    /**
     * Decimal places of the minor unit. No exchange rates live here:
     * amounts in different currencies are never summed together.
     *
     * @var array<string, int>
     */
    public const SCALES = [
        'EUR' => 2,
        'USD' => 2,
        'GBP' => 2,
        'CHF' => 2,
    ];

    public static function supports(string $currency): bool
    {
        return array_key_exists(strtoupper($currency), self::SCALES);
    }

    public static function scale(string $currency): int
    {
        $currency = strtoupper($currency);

        if (! self::supports($currency)) {
            throw new \InvalidArgumentException("Valuta non supportata: {$currency}.");
        }

        return self::SCALES[$currency];
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::SCALES);
    }
}
