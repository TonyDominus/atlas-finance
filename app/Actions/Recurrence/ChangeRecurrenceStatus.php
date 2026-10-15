<?php

declare(strict_types=1);

namespace App\Actions\Recurrence;

use App\Enums\RecurrenceStatus;
use App\Models\RecurringOccurrence;
use App\Models\RecurringRule;
use App\Models\User;
use App\Services\Recurrence\OccurrenceProjector;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChangeRecurrenceStatus
{
    public function __construct(private OccurrenceProjector $projector) {}

    public function handle(User $user, RecurringRule $rule, RecurrenceStatus $status): RecurringRule
    {
        if ($rule->user_id !== $user->id) {
            throw ValidationException::withMessages(['status' => 'Ricorrenza non disponibile.']);
        }

        return DB::transaction(function () use ($rule, $status) {
            $locked = RecurringRule::query()->whereKey($rule->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill(['status' => $status])->save();
            $this->projector->sync($locked);

            return $locked;
        });
    }

    public function delete(User $user, RecurringRule $rule): void
    {
        if ($rule->user_id !== $user->id) {
            throw ValidationException::withMessages(['status' => 'Ricorrenza non disponibile.']);
        }

        $linked = RecurringOccurrence::query()
            ->where('recurring_rule_id', $rule->id)
            ->whereNotNull('journal_entry_id')
            ->exists();

        if ($linked) {
            throw ValidationException::withMessages([
                'status' => 'Ci sono occorrenze già collegate a movimenti. Termina la ricorrenza invece di cancellarla.',
            ]);
        }

        $rule->delete();
    }
}
