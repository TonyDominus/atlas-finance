<?php

declare(strict_types=1);

namespace App\Services\Recurrence;

use App\Domain\Money\Money;
use App\Enums\OccurrenceStatus;
use App\Enums\RecurrenceKind;
use App\Enums\RecurrenceStatus;
use App\Models\RecurringOccurrence;
use App\Models\RecurringRule;
use App\Models\User;
use Carbon\CarbonImmutable;

final class RecurrenceReader
{
    /**
     * @return array<string, mixed>
     */
    public function rule(RecurringRule $rule): array
    {
        $rule->loadMissing(['account', 'destinationAccount', 'category', 'counterparty']);
        $next = $rule->occurrences()
            ->where('status', OccurrenceStatus::Forecast)
            ->whereDate('due_on', '>=', now()->toDateString())
            ->orderBy('due_on')
            ->value('due_on');

        return [
            'id' => $rule->id,
            'description' => $rule->description,
            'kind' => $rule->kind->value,
            'kind_label' => $rule->kind->label(),
            'purpose' => $rule->purpose->value,
            'amount' => Money::ofMinor($rule->amount_minor, $rule->currency)->format(),
            'amount_minor' => $rule->amount_minor,
            'amount_is_estimate' => $rule->amount_is_estimate,
            'currency' => $rule->currency,
            'account_id' => $rule->account_id,
            'account' => $rule->account->name,
            'destination_account_id' => $rule->destination_account_id,
            'destination' => $rule->destinationAccount?->name,
            'category_id' => $rule->category_id,
            'category' => $rule->category?->name,
            'counterparty_id' => $rule->counterparty_id,
            'counterparty' => $rule->counterparty?->name,
            'frequency' => $rule->frequency->value,
            'frequency_label' => $rule->frequency->label(),
            'interval' => $rule->interval,
            'interval_unit' => $rule->interval_unit,
            'starts_on' => $rule->starts_on->toDateString(),
            'ends_on' => $rule->ends_on?->toDateString(),
            'max_occurrences' => $rule->max_occurrences,
            'reminder_days' => $rule->reminder_days,
            'status' => $rule->status->value,
            'status_label' => $rule->status->label(),
            'notes' => $rule->notes,
            'next_on' => $next ? CarbonImmutable::parse($next)->toDateString() : null,
            'monthly_minor' => $rule->kind === RecurrenceKind::Income ? 0 : $rule->monthlyEquivalentMinor(),
            'monthly' => Money::ofMinor($rule->kind === RecurrenceKind::Income ? 0 : $rule->monthlyEquivalentMinor(), $rule->currency)->format(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function timeline(User $user, string $from, string $until): array
    {
        $today = now()->toDateString();

        return RecurringOccurrence::query()
            ->where('user_id', $user->id)
            ->whereDate('due_on', '>=', $from)
            ->whereDate('due_on', '<=', $until)
            ->with(['rule.account'])
            ->orderBy('due_on')
            ->orderBy('id')
            ->get()
            ->map(function (RecurringOccurrence $occurrence) use ($today) {
                $rule = $occurrence->rule;
                $due = $occurrence->due_on->toDateString();
                $remind = $occurrence->status === OccurrenceStatus::Forecast
                    && $rule->reminder_days !== null
                    && $due >= $today
                    && $due <= CarbonImmutable::parse($today)->addDays($rule->reminder_days)->toDateString();

                return [
                    'id' => $occurrence->id,
                    'rule_id' => $rule->id,
                    'due_on' => $due,
                    'description' => $rule->description,
                    'kind' => $rule->kind->value,
                    'kind_label' => $rule->kind->label(),
                    'account' => $rule->account->name,
                    'amount' => Money::ofMinor($occurrence->amount_minor, $occurrence->currency)->format(),
                    'amount_minor' => $occurrence->amount_minor,
                    'estimate' => $rule->amount_is_estimate,
                    'status' => $occurrence->status->value,
                    'status_label' => $occurrence->status->label(),
                    'movement_id' => $occurrence->journal_entry_id,
                    'overdue' => $occurrence->status === OccurrenceStatus::Forecast && $due < $today,
                    'reminder' => $remind,
                    'can_settle' => $occurrence->status === OccurrenceStatus::Forecast && $rule->status === RecurrenceStatus::Active,
                ];
            })
            ->all();
    }

    public function monthlyOutflowMinor(User $user, string $currency = 'EUR'): int
    {
        return (int) RecurringRule::query()
            ->where('user_id', $user->id)
            ->where('status', RecurrenceStatus::Active)
            ->where('currency', $currency)
            ->whereIn('kind', [RecurrenceKind::Expense->value, RecurrenceKind::Fee->value])
            ->get()
            ->sum(fn (RecurringRule $rule) => $rule->monthlyEquivalentMinor());
    }
}
