<?php

namespace App\Models;

use App\Enums\TradeSide;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityTrade extends Model
{
    protected $fillable = [
        'user_id',
        'instrument_id',
        'account_id',
        'journal_entry_id',
        'side',
        'quantity_units',
        'gross_minor',
        'fee_minor',
        'cost_minor',
        'currency',
        'traded_on',
    ];

    protected function casts(): array
    {
        return [
            'side' => TradeSide::class,
            'quantity_units' => 'integer',
            'gross_minor' => 'integer',
            'fee_minor' => 'integer',
            'cost_minor' => 'integer',
            'traded_on' => 'date',
        ];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /**
     * Trades whose journal entry is posted and not voided.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereHas('entry', fn (Builder $entry) => $entry
            ->where('status', 'posted')
            ->whereNull('voided_at'));
    }
}
