<?php

declare(strict_types=1);

namespace App\Domain\Money;

use InvalidArgumentException;

/**
 * Immutable monetary amount stored as an integer number of minor units.
 * EUR 12,50 is 1250. Floating point is never used for arithmetic.
 */
final readonly class Money
{
    public string $currency;

    public function __construct(
        public int $amountMinor,
        string $currency = 'EUR',
    ) {
        $this->currency = strtoupper($currency);

        if (! Currency::supports($this->currency)) {
            throw new InvalidArgumentException("Valuta non supportata: {$this->currency}.");
        }
    }

    public static function ofMinor(int $amountMinor, string $currency = 'EUR'): self
    {
        return new self($amountMinor, $currency);
    }

    public static function zero(string $currency = 'EUR'): self
    {
        return new self(0, $currency);
    }

    public static function parse(string $input, string $currency = 'EUR'): self
    {
        $currency = strtoupper($currency);
        $scale = Currency::scale($currency);

        $raw = str_replace(["\u{00A0}", ' ', '€'], '', trim($input));

        if ($raw === '') {
            throw new InvalidArgumentException('Importo mancante.');
        }

        if (str_contains($raw, ',')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        }

        if (! preg_match('/^-?\d+(\.\d+)?$/', $raw)) {
            throw new InvalidArgumentException('Importo non valido.');
        }

        $negative = str_starts_with($raw, '-');
        $raw = ltrim($raw, '-');

        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');

        if (strlen($whole) > 15) {
            throw new InvalidArgumentException('Importo troppo grande.');
        }

        if (strlen($fraction) > $scale) {
            throw new InvalidArgumentException("Troppe cifre decimali per {$currency}.");
        }

        $fraction = str_pad($fraction, $scale, '0');
        $factor = 10 ** $scale;
        $minor = ((int) $whole) * $factor + (int) $fraction;

        return new self($negative ? -$minor : $minor, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountMinor + $other->amountMinor, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountMinor - $other->amountMinor, $this->currency);
    }

    public function abs(): self
    {
        return new self(abs($this->amountMinor), $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amountMinor === 0;
    }

    public function isNegative(): bool
    {
        return $this->amountMinor < 0;
    }

    public function isPositive(): bool
    {
        return $this->amountMinor > 0;
    }

    public function toDecimal(): string
    {
        $scale = Currency::scale($this->currency);
        $negative = $this->amountMinor < 0;
        $absolute = abs($this->amountMinor);
        $factor = 10 ** $scale;
        $whole = intdiv($absolute, $factor);
        $fraction = $scale === 0 ? '' : str_pad((string) ($absolute % $factor), $scale, '0', STR_PAD_LEFT);
        $number = $scale === 0 ? (string) $whole : $whole.'.'.$fraction;

        return $negative ? '-'.$number : $number;
    }

    public function format(): string
    {
        $scale = Currency::scale($this->currency);
        $negative = $this->amountMinor < 0;
        $absolute = abs($this->amountMinor);
        $factor = 10 ** $scale;
        $whole = intdiv($absolute, $factor);
        $grouped = number_format($whole, 0, ',', '.');
        $number = $scale === 0
            ? $grouped
            : $grouped.','.str_pad((string) ($absolute % $factor), $scale, '0', STR_PAD_LEFT);
        $suffix = $this->currency === 'EUR' ? '€' : $this->currency;

        return ($negative ? '-' : '').$number.' '.$suffix;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($other->currency !== $this->currency) {
            throw new InvalidArgumentException('Non si sommano importi in valute diverse.');
        }
    }
}
