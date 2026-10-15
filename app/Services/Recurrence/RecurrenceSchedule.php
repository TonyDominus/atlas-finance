<?php

declare(strict_types=1);

namespace App\Services\Recurrence;

use App\Enums\RecurrenceFrequency;
use Carbon\CarbonImmutable;

/**
 * Builds occurrence dates from an anchor day.
 * Month-based rules clamp independently: 31 January, 28 February, 31 March.
 */
final class RecurrenceSchedule
{
    /**
     * @return list<string>
     */
    public function dates(
        string $startsOn,
        string $frequency,
        int $interval,
        ?string $intervalUnit,
        ?string $endsOn,
        ?int $maxOccurrences,
        string $windowStart,
        string $windowEnd,
    ): array {
        $start = CarbonImmutable::parse($startsOn)->startOfDay();
        $windowFrom = CarbonImmutable::parse($windowStart)->startOfDay();
        $windowTo = CarbonImmutable::parse($windowEnd)->startOfDay();
        $end = $endsOn ? CarbonImmutable::parse($endsOn)->startOfDay() : null;
        $interval = max(1, $interval);
        $anchor = (int) $start->day;
        $frequency = RecurrenceFrequency::from($frequency);
        $dates = [];

        for ($index = 0; $index < 500; $index++) {
            if ($maxOccurrences !== null && $index >= $maxOccurrences) {
                break;
            }

            $date = $this->at($start, $anchor, $frequency, $interval, $intervalUnit, $index);

            if ($end && $date->greaterThan($end)) {
                break;
            }

            if ($date->greaterThan($windowTo)) {
                break;
            }

            if ($date->greaterThanOrEqualTo($windowFrom)) {
                $dates[] = $date->toDateString();
            }
        }

        return $dates;
    }

    private function at(
        CarbonImmutable $start,
        int $anchor,
        RecurrenceFrequency $frequency,
        int $interval,
        ?string $intervalUnit,
        int $index,
    ): CarbonImmutable {
        $unit = $frequency === RecurrenceFrequency::Custom ? ($intervalUnit ?: 'month') : null;

        if ($frequency === RecurrenceFrequency::Daily || $unit === 'day') {
            return $start->addDays($index * $interval);
        }

        if ($frequency === RecurrenceFrequency::Weekly || $unit === 'week') {
            return $start->addWeeks($index * $interval);
        }

        $months = match ($frequency) {
            RecurrenceFrequency::Quarterly => $index * 3 * $interval,
            RecurrenceFrequency::Semiannual => $index * 6 * $interval,
            RecurrenceFrequency::Yearly => $index * 12 * $interval,
            default => $index * $interval,
        };

        $shifted = $start->startOfMonth()->addMonthsNoOverflow($months);
        $day = min($anchor, $shifted->daysInMonth);

        return $shifted->day($day);
    }
}
