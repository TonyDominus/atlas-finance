<?php

use App\Services\Recurrence\RecurrenceSchedule;

test('monthly recurrence clamps missing days and returns to the anchor', function () {
    $dates = app(RecurrenceSchedule::class)->dates(
        '2026-01-31',
        'monthly',
        1,
        null,
        null,
        3,
        '2026-01-01',
        '2026-12-31',
    );

    expect($dates)->toBe(['2026-01-31', '2026-02-28', '2026-03-31']);
});

test('max occurrences counts from the start, not from the window', function () {
    $dates = app(RecurrenceSchedule::class)->dates(
        '2026-01-05',
        'monthly',
        1,
        null,
        null,
        2,
        '2026-02-01',
        '2026-12-31',
    );

    expect($dates)->toBe(['2026-02-05']);
});
