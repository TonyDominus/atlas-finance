<?php

declare(strict_types=1);

namespace App\Actions\Movements;

use App\Enums\CategoryKind;
use App\Enums\JournalEntryType;
use App\Models\Category;
use App\Models\Counterparty;
use App\Models\JournalEntry;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateMovementDetails
{
    /**
     * @param  array{description: string, notes?: string|null, category_id?: int|null, counterparty_id?: int|null, tags?: list<string>}  $data
     */
    public function handle(User $user, JournalEntry $entry, array $data): JournalEntry
    {
        if ($entry->user_id !== $user->id) {
            throw ValidationException::withMessages(['description' => 'Movimento non disponibile.']);
        }

        if ($entry->isVoided() || $entry->type === JournalEntryType::Reversal) {
            throw ValidationException::withMessages(['description' => 'Questo movimento non si modifica.']);
        }

        return DB::transaction(function () use ($user, $entry, $data) {
            $categoryId = $data['category_id'] ?? null;

            if (in_array($entry->type, [JournalEntryType::Transfer, JournalEntryType::InvestmentBuy, JournalEntryType::InvestmentSell, JournalEntryType::OpeningBalance], true)) {
                $categoryId = null;
            } elseif ($categoryId) {
                $expected = $entry->type === JournalEntryType::Income ? CategoryKind::Income : CategoryKind::Expense;
                $category = Category::query()->where('user_id', $user->id)->find($categoryId);

                if (! $category || $category->kind !== $expected) {
                    throw ValidationException::withMessages(['category_id' => 'Categoria non valida.']);
                }
            }

            $counterpartyId = $data['counterparty_id'] ?? null;

            if ($counterpartyId && ! Counterparty::query()->where('user_id', $user->id)->whereKey($counterpartyId)->exists()) {
                throw ValidationException::withMessages(['counterparty_id' => 'Controparte non trovata.']);
            }

            $entry->fill([
                'description' => $data['description'],
                'notes' => $data['notes'] ?? null,
                'category_id' => $categoryId,
                'counterparty_id' => $counterpartyId,
            ])->save();

            $ids = [];

            foreach ($data['tags'] ?? [] as $name) {
                $name = trim($name);

                if ($name === '') {
                    continue;
                }

                $ids[] = Tag::query()->firstOrCreate([
                    'user_id' => $user->id,
                    'name' => $name,
                ])->id;
            }

            $entry->tags()->sync($ids);

            return $entry->load(['lines.ledgerAccount', 'category', 'counterparty', 'tags']);
        });
    }
}
