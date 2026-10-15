<?php

declare(strict_types=1);

namespace App\Actions\Movements;

use App\Enums\JournalEntryStatus;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PostForecast
{
    public function handle(User $user, JournalEntry $entry): JournalEntry
    {
        if ($entry->user_id !== $user->id) {
            throw ValidationException::withMessages(['status' => 'Movimento non disponibile.']);
        }

        return DB::transaction(function () use ($entry) {
            $locked = JournalEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== JournalEntryStatus::Forecast || $locked->isVoided()) {
                throw ValidationException::withMessages([
                    'status' => 'Solo una previsione ancora aperta si può contabilizzare.',
                ]);
            }

            $locked->forceFill(['status' => JournalEntryStatus::Posted])->save();

            return $locked;
        });
    }
}
