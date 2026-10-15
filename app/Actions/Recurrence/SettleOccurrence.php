<?php

declare(strict_types=1);

namespace App\Actions\Recurrence;

use App\Actions\Movements\RecordMovement;
use App\Domain\Ledger\LedgerException;
use App\Domain\Money\Money;
use App\Domain\Movements\MovementData;
use App\Enums\JournalEntryType;
use App\Enums\JournalOrigin;
use App\Enums\OccurrenceStatus;
use App\Enums\RecurrenceKind;
use App\Models\JournalEntry;
use App\Models\RecurringOccurrence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SettleOccurrence
{
    public function __construct(private RecordMovement $movements) {}

    public function realize(User $user, RecurringOccurrence $occurrence): JournalEntry
    {
        return DB::transaction(function () use ($user, $occurrence) {
            $locked = $this->lockForecast($user, $occurrence);
            $rule = $locked->rule()->lockForUpdate()->firstOrFail();

            try {
                $entry = $this->movements->handle($user, new MovementData(
                    type: $this->type($rule->kind),
                    amount: Money::ofMinor($locked->amount_minor, $locked->currency),
                    occurredOn: $locked->due_on->toDateString(),
                    bookedOn: $locked->due_on->toDateString(),
                    description: $rule->description,
                    accountId: $rule->account_id,
                    destinationAccountId: $rule->destination_account_id,
                    fee: null,
                    categoryId: $rule->category_id,
                    counterpartyId: $rule->counterparty_id,
                    counterpartyName: null,
                    notes: 'Contabilizzato dalla ricorrenza.',
                    tags: [],
                    externalId: 'recurrence-'.$locked->id,
                    origin: JournalOrigin::Manual,
                ));
            } catch (LedgerException $exception) {
                throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
            }

            $locked->forceFill([
                'status' => OccurrenceStatus::Linked,
                'journal_entry_id' => $entry->id,
            ])->save();

            return $entry;
        });
    }

    public function link(User $user, RecurringOccurrence $occurrence, JournalEntry $entry): void
    {
        DB::transaction(function () use ($user, $occurrence, $entry) {
            $locked = $this->lockForecast($user, $occurrence);
            $movement = JournalEntry::query()->whereKey($entry->id)->lockForUpdate()->first();

            if (! $movement || $movement->user_id !== $user->id || $movement->isVoided() || $movement->status->value !== 'posted') {
                throw ValidationException::withMessages(['journal_entry_id' => 'Il movimento da collegare non è contabilizzato.']);
            }

            if ($movement->currency !== $locked->currency) {
                throw ValidationException::withMessages(['journal_entry_id' => 'La valuta del movimento non coincide.']);
            }

            $already = RecurringOccurrence::query()->where('journal_entry_id', $movement->id)->exists();

            if ($already) {
                throw ValidationException::withMessages(['journal_entry_id' => 'Questo movimento è già collegato a un\'occorrenza.']);
            }

            $locked->forceFill([
                'status' => OccurrenceStatus::Linked,
                'journal_entry_id' => $movement->id,
            ])->save();
        });
    }

    public function skip(User $user, RecurringOccurrence $occurrence): void
    {
        DB::transaction(function () use ($user, $occurrence) {
            $locked = $this->lockForecast($user, $occurrence);
            $locked->forceFill(['status' => OccurrenceStatus::Skipped])->save();
        });
    }

    public function changeAmount(User $user, RecurringOccurrence $occurrence, string $amount): void
    {
        DB::transaction(function () use ($user, $occurrence, $amount) {
            $locked = $this->lockForecast($user, $occurrence);

            try {
                $money = Money::parse($amount, $locked->currency);
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
            }

            if (! $money->isPositive()) {
                throw ValidationException::withMessages(['amount' => 'L\'importo deve essere maggiore di zero.']);
            }

            $locked->forceFill([
                'amount_minor' => $money->amountMinor,
                'amount_locked' => true,
            ])->save();
        });
    }

    private function lockForecast(User $user, RecurringOccurrence $occurrence): RecurringOccurrence
    {
        $locked = RecurringOccurrence::query()->whereKey($occurrence->id)->lockForUpdate()->firstOrFail();

        if ($locked->user_id !== $user->id) {
            throw ValidationException::withMessages(['status' => 'Occorrenza non disponibile.']);
        }

        if ($locked->status !== OccurrenceStatus::Forecast) {
            throw ValidationException::withMessages(['status' => 'Questa occorrenza non è più una previsione.']);
        }

        return $locked;
    }

    private function type(RecurrenceKind $kind): JournalEntryType
    {
        return match ($kind) {
            RecurrenceKind::Expense => JournalEntryType::Expense,
            RecurrenceKind::Income => JournalEntryType::Income,
            RecurrenceKind::Transfer => JournalEntryType::Transfer,
            RecurrenceKind::Fee => JournalEntryType::Fee,
        };
    }
}
