<?php

declare(strict_types=1);

namespace App\Domain\Investments;

use InvalidArgumentException;

/**
 * Quantities and unit prices stored as integers with six implied decimals.
 * 10,5 shares is 10500000. All multiplication goes through bcmath.
 */
final class Decimal
{
    public const SCALE = 6;

    public const FACTOR = 1_000_000;

    public static function parse(string $input, string $field = 'Valore'): int
    {
        $raw = str_replace(["\u{00A0}", ' '], '', trim($input));

        if ($raw === '') {
            throw new InvalidArgumentException("{$field} mancante.");
        }

        if (str_contains($raw, ',')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        }

        if (! preg_match('/^\d+(\.\d+)?$/', $raw)) {
            throw new InvalidArgumentException("{$field} non valido.");
        }

        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');

        if (strlen($whole) > 12) {
            throw new InvalidArgumentException("{$field} troppo grande.");
        }

        if (strlen($fraction) > self::SCALE) {
            throw new InvalidArgumentException("{$field}: al massimo ".self::SCALE.' decimali.');
        }

        return ((int) $whole) * self::FACTOR + (int) str_pad($fraction, self::SCALE, '0');
    }

    /**
     * Italian formatting with trailing zeros trimmed, keeping at least $minDecimals.
     */
    public static function format(int $units, int $minDecimals = 0): string
    {
        $negative = $units < 0;
        $absolute = abs($units);
        $whole = intdiv($absolute, self::FACTOR);
        $fraction = rtrim(str_pad((string) ($absolute % self::FACTOR), self::SCALE, '0', STR_PAD_LEFT), '0');
        $fraction = str_pad($fraction, $minDecimals, '0');
        $number = number_format($whole, 0, ',', '.').($fraction !== '' ? ','.$fraction : '');

        return ($negative ? '-' : '').$number;
    }

    /**
     * Market value in minor units of a currency with $scale decimals, rounded half up.
     */
    public static function valueMinor(int $quantityUnits, int $priceUnits, int $scale = 2): int
    {
        $product = bcmul((string) $quantityUnits, (string) $priceUnits, 0);
        $divisor = bcpow('10', (string) (self::SCALE * 2 - $scale), 0);

        return self::roundDiv($product, $divisor);
    }

    /**
     * Unit price (six decimals) from an amount in minor units and a quantity.
     */
    public static function unitPrice(int $amountMinor, int $quantityUnits, int $scale = 2): int
    {
        if ($quantityUnits <= 0) {
            return 0;
        }

        $numerator = bcmul((string) $amountMinor, bcpow('10', (string) (self::SCALE * 2 - $scale), 0), 0);

        return self::roundDiv($numerator, (string) $quantityUnits);
    }

    /**
     * $total * $part / $whole with half-up rounding, without overflowing 64 bit.
     */
    public static function proportion(int $total, int $part, int $whole): int
    {
        if ($whole === 0) {
            return 0;
        }

        return self::roundDiv(bcmul((string) $total, (string) $part, 0), (string) $whole);
    }

    private static function roundDiv(string $numerator, string $divisor): int
    {
        $negative = str_starts_with($numerator, '-') xor str_starts_with($divisor, '-');
        $numerator = ltrim($numerator, '-');
        $divisor = ltrim($divisor, '-');
        $quotient = bcdiv(bcadd(bcmul($numerator, '2', 0), $divisor, 0), bcmul($divisor, '2', 0), 0);

        return (int) ($negative ? '-'.$quotient : $quotient);
    }
}
