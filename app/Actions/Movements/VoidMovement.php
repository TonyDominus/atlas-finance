<?php

declare(strict_types=1);

namespace App\Actions\Movements;

use App\Enums\JournalEntryStatus;
use App\Enums\OccurrenceStatus;
use App\Models\JournalEntry;
use App\Models\RecurringOccurrence;
use App\Models\SecurityTrade;
use App\Models\User;
use App\Services\Ledger\JournalPoster;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class VoidMovement
{
    public function __construct(private JournalPoster $poster) {}

    public function handle(User $user, JournalEntry $entry, string $reason, bool $fromTrade = false): void
    {
        if ($entry->user_id !== $user->id) {
            throw ValidationException::withMessages(['reason' => 'Movimento non disponibile.']);
        }

        if (! $fromTrade && SecurityTrade::query()->where('journal_entry_id', $entry->id)->exists()) {
            throw ValidationException::withMessages([
                'reason' => 'È un\'operazione su titoli: annullala dalla pagina Investimenti, così quantità e costo medio restano coerenti.',
            ]);
        }

        DB::transaction(function () use ($user, $entry, $reason) {
            $locked = JournalEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === JournalEntryStatus::Forecast) {
                $locked->forceFill([
                    'status' => JournalEntryStatus::Void,
                    'voided_at' => now(),
                    'void_reason' => $reason,
                    'voided_by' => $user->id,
                ])->save();
                $this->releaseOccurrence($locked);

                return;
            }

            $children = JournalEntry::query()
                ->where('user_id', $user->id)
                ->where('related_entry_id', $entry->id)
                ->whereNull('voided_at')
                ->get();

            foreach ($children as $child) {
                $this->poster->void($user, $child, $reason);
            }

            $this->poster->void($user, $locked, $reason);
            $this->releaseOccurrence($locked);
        });
    }

    private function releaseOccurrence(JournalEntry $entry): void
    {
        RecurringOccurrence::query()
            ->where('journal_entry_id', $entry->id)
            ->update([
                'journal_entry_id' => null,
                'status' => OccurrenceStatus::Forecast->value,
            ]);
    }
}
