<?php

declare(strict_types=1);

namespace App\Services\Recurrence;

use App\Enums\OccurrenceStatus;
use App\Enums\RecurrenceStatus;
use App\Models\RecurringOccurrence;
use App\Models\RecurringRule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class OccurrenceProjector
{
    public function __construct(private RecurrenceSchedule $schedule) {}

    public function sync(RecurringRule $rule, ?string $until = null): void
    {
        DB::transaction(function () use ($rule, $until) {
            $locked = RecurringRule::query()->whereKey($rule->id)->lockForUpdate()->firstOrFail();
            $horizon = $until ?? $this->horizon($locked);

            if ($locked->status !== RecurrenceStatus::Active) {
                RecurringOccurrence::query()
                    ->where('recurring_rule_id', $locked->id)
                    ->where('status', OccurrenceStatus::Forecast)
                    ->whereDate('due_on', '>=', now()->toDateString())
                    ->delete();

                return;
            }

            $dates = $this->schedule->dates(
                $locked->starts_on->toDateString(),
                $locked->frequency->value,
                (int) $locked->interval,
                $locked->interval_unit,
                $locked->ends_on?->toDateString(),
                $locked->max_occurrences,
                $locked->starts_on->toDateString(),
                $horizon,
            );

            $existing = RecurringOccurrence::query()
                ->where('recurring_rule_id', $locked->id)
                ->get()
                ->keyBy(fn (RecurringOccurrence $occurrence) => $occurrence->due_on->toDateString());

            foreach ($dates as $date) {
                /** @var RecurringOccurrence|null $occurrence */
                $occurrence = $existing->get($date);

                if ($occurrence) {
                    if ($occurrence->status === OccurrenceStatus::Forecast && ! $occurrence->amount_locked) {
                        $occurrence->forceFill([
                            'amount_minor' => $locked->amount_minor,
                            'currency' => $locked->currency,
                        ])->save();
                    }

                    continue;
                }

                RecurringOccurrence::query()->create([
                    'user_id' => $locked->user_id,
                    'recurring_rule_id' => $locked->id,
                    'due_on' => $date,
                    'amount_minor' => $locked->amount_minor,
                    'currency' => $locked->currency,
                    'status' => OccurrenceStatus::Forecast,
                ]);
            }

            RecurringOccurrence::query()
                ->where('recurring_rule_id', $locked->id)
                ->where('status', OccurrenceStatus::Forecast)
                ->get()
                ->each(function (RecurringOccurrence $occurrence) use ($dates) {
                    if (! in_array($occurrence->due_on->toDateString(), $dates, true)) {
                        $occurrence->delete();
                    }
                });
        });
    }

    public function horizon(RecurringRule $rule): string
    {
        $default = CarbonImmutable::now()->addMonthsNoOverflow(18)->toDateString();

        if ($rule->ends_on && $rule->ends_on->toDateString() < $default) {
            return $rule->ends_on->toDateString();
        }

        return $default;
    }
}
