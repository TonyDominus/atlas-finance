<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\Domain\Ledger\DuplicateMovementException;
use App\Domain\Ledger\LedgerException;
use App\Domain\Ledger\UnbalancedJournalException;
use App\Enums\JournalEntryStatus;
use App\Enums\JournalEntryType;
use App\Enums\JournalOrigin;
use App\Enums\JournalSide;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class JournalPoster
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array{ledger_account_id: int, side: JournalSide, amount_minor: int, currency: string, memo?: string|null}>  $lines
     */
    public function post(User $user, array $attributes, array $lines): JournalEntry
    {
        return DB::transaction(function () use ($user, $attributes, $lines) {
            $currency = strtoupper((string) $attributes['currency']);
            $prepared = $this->prepareLines($user, $currency, $lines);
            $origin = $attributes['origin'] instanceof JournalOrigin
                ? $attributes['origin']
                : JournalOrigin::from((string) ($attributes['origin'] ?? JournalOrigin::Manual->value));
            $externalId = $attributes['external_id'] ?? null;
            $externalId = is_string($externalId) && trim($externalId) !== '' ? trim($externalId) : null;

            if ($externalId !== null) {
                $duplicate = JournalEntry::query()
                    ->where('user_id', $user->id)
                    ->where('origin', $origin->value)
                    ->where('external_id', $externalId)
                    ->lockForUpdate()
                    ->exists();

                if ($duplicate) {
                    throw new DuplicateMovementException('Questo identificativo esterno è già stato registrato.');
                }
            }

            try {
                $entry = JournalEntry::query()->create([
                    'user_id' => $user->id,
                    'type' => $attributes['type'],
                    'status' => $attributes['status'] ?? JournalEntryStatus::Posted,
                    'origin' => $origin,
                    'occurred_on' => $attributes['occurred_on'],
                    'booked_on' => $attributes['booked_on'],
                    'description' => $attributes['description'],
                    'notes' => $attributes['notes'] ?? null,
                    'currency' => $currency,
                    'category_id' => $attributes['category_id'] ?? null,
                    'counterparty_id' => $attributes['counterparty_id'] ?? null,
                    'external_id' => $externalId,
                    'reverses_entry_id' => $attributes['reverses_entry_id'] ?? null,
                    'related_entry_id' => $attributes['related_entry_id'] ?? null,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new DuplicateMovementException('Questo identificativo esterno è già stato registrato.');
            }

            foreach ($prepared as $line) {
                JournalLine::query()->create([
                    'journal_entry_id' => $entry->id,
                    'ledger_account_id' => $line['ledger_account_id'],
                    'side' => $line['side'],
                    'amount_minor' => $line['amount_minor'],
                    'currency' => $currency,
                    'memo' => $line['memo'] ?? null,
                ]);
            }

            return $entry->load('lines');
        });
    }

    public function void(User $user, JournalEntry $entry, string $reason): JournalEntry
    {
        return DB::transaction(function () use ($user, $entry, $reason) {
            $locked = JournalEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if ($locked->user_id !== $user->id) {
                throw new LedgerException('Movimento non disponibile.');
            }

            if ($locked->isVoided()) {
                throw new LedgerException('Il movimento è già annullato.');
            }

            if ($locked->type === JournalEntryType::Reversal) {
                throw new LedgerException('Uno storno non si annulla. Annulla il movimento originale.');
            }

            if ($locked->status !== JournalEntryStatus::Posted) {
                throw new LedgerException('Si possono annullare solo i movimenti contabilizzati.');
            }

            $locked->load('lines');

            $reversal = $this->post($user, [
                'type' => JournalEntryType::Reversal,
                'status' => JournalEntryStatus::Posted,
                'origin' => $locked->origin,
                'occurred_on' => now()->toDateString(),
                'booked_on' => now()->toDateString(),
                'description' => 'Storno: '.$locked->description,
                'notes' => $reason,
                'currency' => $locked->currency,
                'category_id' => $locked->category_id,
                'counterparty_id' => $locked->counterparty_id,
                'reverses_entry_id' => $locked->id,
            ], $locked->lines->map(fn (JournalLine $line) => [
                'ledger_account_id' => $line->ledger_account_id,
                'side' => $line->side->opposite(),
                'amount_minor' => $line->amount_minor,
                'currency' => $line->currency,
                'memo' => $line->memo,
            ])->all());

            $locked->forceFill([
                'voided_at' => now(),
                'void_reason' => $reason,
                'voided_by' => $user->id,
            ])->save();

            return $reversal;
        });
    }

    /**
     * @param  list<array{ledger_account_id: int, side: JournalSide, amount_minor: int, currency: string, memo?: string|null}>  $lines
     * @return list<array{ledger_account_id: int, side: JournalSide, amount_minor: int, currency: string, memo?: string|null}>
     */
    private function prepareLines(User $user, string $currency, array $lines): array
    {
        if (count($lines) < 2) {
            throw new UnbalancedJournalException('Una scrittura deve avere almeno due righe.');
        }

        $ids = collect($lines)->pluck('ledger_account_id')->unique()->values();
        $ledgers = LedgerAccount::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        if ($ledgers->count() !== $ids->count()) {
            throw new UnbalancedJournalException('Conto contabile non valido.');
        }

        $debit = 0;
        $credit = 0;

        foreach ($lines as $line) {
            $amount = (int) $line['amount_minor'];

            if ($amount <= 0) {
                throw new UnbalancedJournalException('Gli importi delle righe devono essere positivi.');
            }

            if (strtoupper($line['currency']) !== $currency) {
                throw new UnbalancedJournalException('Le righe devono usare la stessa valuta della scrittura.');
            }

            /** @var LedgerAccount $ledger */
            $ledger = $ledgers->get($line['ledger_account_id']);

            if ($ledger->currency !== $currency) {
                throw new UnbalancedJournalException('La valuta non coincide con il conto contabile.');
            }

            if ($line['side'] === JournalSide::Debit) {
                $debit += $amount;
            } else {
                $credit += $amount;
            }
        }

        if ($debit !== $credit) {
            throw new UnbalancedJournalException('Scrittura non bilanciata: dare e avere non coincidono.');
        }

        return $lines;
    }
}
