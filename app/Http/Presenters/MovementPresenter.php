<?php

namespace App\Http\Presenters;

use App\Domain\Money\Money;
use App\Enums\JournalEntryType;
use App\Enums\JournalSide;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\SecurityTrade;

final class MovementPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function summary(JournalEntry $entry): array
    {
        $minor = (int) ($entry->lines->first()->amount_minor ?? 0);

        return [
            'id' => $entry->id,
            'type' => $entry->type->value,
            'type_label' => $entry->type->label(),
            'description' => $entry->description,
            'occurred_on' => $entry->occurred_on->toDateString(),
            'booked_on' => $entry->booked_on->toDateString(),
            'amount' => Money::ofMinor($minor, $entry->currency)->format(),
            'amount_minor' => $minor,
            'direction' => $this->direction($entry),
            'category' => $entry->category?->name,
            'counterparty' => $entry->counterparty?->name,
            'voided' => $entry->isVoided(),
            'status' => $entry->status->value,
            'status_label' => $entry->isVoided() ? 'Annullato' : $entry->status->label(),
            'origin' => $entry->origin->label(),
            'accounts' => $entry->lines
                ->map(fn (JournalLine $line) => $line->ledgerAccount?->financialAccount?->name)
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(JournalEntry $entry): array
    {
        $isTrade = SecurityTrade::query()->where('journal_entry_id', $entry->id)->exists();

        return [
            ...$this->summary($entry),
            'notes' => $entry->notes,
            'void_reason' => $entry->void_reason,
            'currency' => $entry->currency,
            'category_id' => $entry->category_id,
            'counterparty_id' => $entry->counterparty_id,
            'external_id' => $entry->external_id,
            'tags' => $entry->tags->pluck('name')->values()->all(),
            'can_edit' => ! $entry->isVoided() && $entry->type !== JournalEntryType::Reversal,
            'can_void' => ! $entry->isVoided()
                && $entry->type !== JournalEntryType::Reversal
                && ! $isTrade,
            'is_trade' => $isTrade,
            'lines' => $entry->lines->map(fn (JournalLine $line) => [
                'account' => $line->ledgerAccount->name,
                'side' => $line->side->label(),
                'amount' => Money::ofMinor($line->amount_minor, $line->currency)->format(),
            ])->all(),
            'attachments' => $entry->attachments->map(fn ($file) => [
                'id' => $file->id,
                'name' => $file->original_name,
            ])->all(),
            'related' => $entry->relatedEntries->map(fn (JournalEntry $related) => [
                'id' => $related->id,
                'description' => $related->description,
                'voided' => $related->isVoided(),
            ])->all(),
        ];
    }

    private function direction(JournalEntry $entry): string
    {
        if ($entry->type === JournalEntryType::Transfer) {
            return 'neutral';
        }

        $financial = $entry->lines->filter(
            fn (JournalLine $line) => $line->ledgerAccount?->financial_account_id !== null,
        );
        $incoming = $financial->contains(fn (JournalLine $line) => $line->side === JournalSide::Debit);
        $outgoing = $financial->contains(fn (JournalLine $line) => $line->side === JournalSide::Credit);

        if ($incoming && ! $outgoing) {
            return 'in';
        }

        if ($outgoing && ! $incoming) {
            return 'out';
        }

        return 'neutral';
    }
}
