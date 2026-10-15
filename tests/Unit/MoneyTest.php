<?php

use App\Domain\Money\Money;

test('parses italian and canonical amounts into minor units', function () {
    expect(Money::parse('1.234,56')->amountMinor)->toBe(123456)
        ->and(Money::parse('12,50')->amountMinor)->toBe(1250)
        ->and(Money::parse('12.50')->amountMinor)->toBe(1250)
        ->and(Money::parse('10')->amountMinor)->toBe(1000)
        ->and(Money::parse('-10,00')->amountMinor)->toBe(-1000)
        ->and(Money::parse('0,00')->isZero())->toBeTrue();
});

test('rejects extra precision and empty amounts', function () {
    expect(fn () => Money::parse('10,555'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Money::parse(''))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Money::parse('12.50', 'XYZ'))->toThrow(InvalidArgumentException::class);
});

test('formats and adds without floating point storage', function () {
    $sum = Money::parse('0,10')->add(Money::parse('0,20'));

    expect($sum->amountMinor)->toBe(30)
        ->and($sum->format())->toBe('0,30 €')
        ->and(Money::parse('1.234,56')->format())->toBe('1.234,56 €')
        ->and(fn () => Money::ofMinor(100, 'EUR')->add(Money::ofMinor(100, 'USD')))
        ->toThrow(InvalidArgumentException::class);
});
