<?php

use App\Domain\Investments\Decimal;

test('quantities parse Italian and dotted input into six implied decimals', function () {
    expect(Decimal::parse('10,5'))->toBe(10_500_000)
        ->and(Decimal::parse('1.234,000001'))->toBe(1_234_000_001)
        ->and(Decimal::parse('0.25'))->toBe(250_000)
        ->and(fn () => Decimal::parse('1,1234567'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Decimal::parse('-1'))->toThrow(InvalidArgumentException::class);
});

test('formatting trims trailing zeros', function () {
    expect(Decimal::format(10_500_000))->toBe('10,5')
        ->and(Decimal::format(1_234_000_000))->toBe('1.234')
        ->and(Decimal::format(118_420_000, 2))->toBe('118,42');
});

test('value and unit price round half up without floats', function () {
    expect(Decimal::valueMinor(10_500_000, 118_420_000))->toBe(124341)
        ->and(Decimal::valueMinor(1, 5_000_000))->toBe(0)
        ->and(Decimal::unitPrice(100000, 3_000_000))->toBe(333_333_333)
        ->and(Decimal::proportion(5001, 1, 2))->toBe(2501)
        ->and(Decimal::proportion(-5001, 1, 2))->toBe(-2501);
});
