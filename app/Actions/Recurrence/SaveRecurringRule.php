<?php

declare(strict_types=1);

namespace App\Actions\Recurrence;

use App\Domain\Money\Money;
use App\Enums\RecurrenceFrequency;
use App\Enums\RecurrenceKind;
use App\Enums\RecurrencePurpose;
use App\Enums\RecurrenceStatus;
use App\Models\Account;
use App\Models\Category;
use App\Models\Counterparty;
use App\Models\RecurringRule;
use App\Models\User;
use App\Services\Recurrence\OccurrenceProjector;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class SaveRecurringRule
{
    public function __construct(private OccurrenceProjector $projector) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(User $user, array $input): RecurringRule
    {
        return DB::transaction(function () use ($user, $input) {
            $rule = new RecurringRule(['user_id' => $user->id]);
            $this->fill($user, $rule, $input);
            $rule->save();
            $this->projector->sync($rule);

            return $rule->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, RecurringRule $rule, array $input): RecurringRule
    {
        if ($rule->user_id !== $user->id) {
            throw ValidationException::withMessages(['description' => 'Ricorrenza non disponibile.']);
        }

        return DB::transaction(function () use ($user, $rule, $input) {
            $locked = RecurringRule::query()->whereKey($rule->id)->lockForUpdate()->firstOrFail();
            $this->fill($user, $locked, $input);
            $locked->save();
            $this->projector->sync($locked);

            return $locked->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function fill(User $user, RecurringRule $rule, array $input): void
    {
        $account = Account::query()->where('user_id', $user->id)->find($input['account_id'] ?? null);

        if (! $account) {
            throw ValidationException::withMessages(['account_id' => 'Conto non trovato.']);
        }

        try {
            $amount = Money::parse((string) $input['amount'], $account->currency);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
        }

        if (! $amount->isPositive()) {
            throw ValidationException::withMessages(['amount' => 'L\'importo deve essere maggiore di zero.']);
        }

        $kind = RecurrenceKind::from((string) $input['kind']);
        $frequency = RecurrenceFrequency::from((string) $input['frequency']);
        $destinationId = null;

        if ($kind === RecurrenceKind::Transfer) {
            $destinationId = (int) ($input['destination_account_id'] ?? 0);
            $destination = Account::query()->where('user_id', $user->id)->find($destinationId);

            if (! $destination || $destination->id === $account->id) {
                throw ValidationException::withMessages(['destination_account_id' => 'Seleziona un conto di destinazione diverso.']);
            }

            if ($destination->currency !== $account->currency) {
                throw ValidationException::withMessages(['destination_account_id' => 'I due conti devono avere la stessa valuta.']);
            }
        }

        $categoryId = $this->categoryId($user, $input['category_id'] ?? null, $kind);
        $counterpartyId = $this->counterpartyId($user, $input['counterparty_id'] ?? null);
        $startsOn = (string) $input['starts_on'];

        $rule->fill([
            'account_id' => $account->id,
            'destination_account_id' => $destinationId,
            'category_id' => $categoryId,
            'counterparty_id' => $counterpartyId,
            'kind' => $kind,
            'purpose' => RecurrencePurpose::from((string) ($input['purpose'] ?? $rule->purpose?->value ?? RecurrencePurpose::General->value)),
            'description' => trim((string) $input['description']),
            'amount_minor' => $amount->amountMinor,
            'amount_is_estimate' => (bool) ($input['amount_is_estimate'] ?? false),
            'currency' => $account->currency,
            'frequency' => $frequency,
            'interval' => max(1, (int) ($input['interval'] ?? 1)),
            'interval_unit' => $frequency === RecurrenceFrequency::Custom ? (string) ($input['interval_unit'] ?? 'month') : null,
            'anchor_day' => (int) substr($startsOn, 8, 2),
            'starts_on' => $startsOn,
            'ends_on' => $input['ends_on'] ?: null,
            'max_occurrences' => $input['max_occurrences'] !== null && $input['max_occurrences'] !== ''
                ? (int) $input['max_occurrences']
                : null,
            'reminder_days' => $input['reminder_days'] !== null && $input['reminder_days'] !== ''
                ? (int) $input['reminder_days']
                : null,
            'status' => $rule->exists ? $rule->status : RecurrenceStatus::Active,
            'notes' => $input['notes'] ?: null,
        ]);
    }

    private function categoryId(User $user, mixed $id, RecurrenceKind $kind): ?int
    {
        if (! $id || $kind === RecurrenceKind::Transfer) {
            if ($kind === RecurrenceKind::Fee) {
                return Category::query()->where('user_id', $user->id)->where('slug', 'commissioni-bancarie')->value('id');
            }

            return null;
        }

        $category = Category::query()->where('user_id', $user->id)->find($id);

        if (! $category) {
            throw ValidationException::withMessages(['category_id' => 'Categoria non trovata.']);
        }

        return $category->id;
    }

    private function counterpartyId(User $user, mixed $id): ?int
    {
        if (! $id) {
            return null;
        }

        $counterparty = Counterparty::query()->where('user_id', $user->id)->find($id);

        if (! $counterparty) {
            throw ValidationException::withMessages(['counterparty_id' => 'Controparte non trovata.']);
        }

        return $counterparty->id;
    }
}
