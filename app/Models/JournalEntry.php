<?php

namespace App\Models;

use App\Enums\JournalEntryStatus;
use App\Enums\JournalEntryType;
use App\Enums\JournalOrigin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class JournalEntry extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'status',
        'origin',
        'occurred_on',
        'booked_on',
        'description',
        'notes',
        'currency',
        'category_id',
        'counterparty_id',
        'external_id',
        'reverses_entry_id',
        'related_entry_id',
        'voided_at',
        'void_reason',
        'voided_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => JournalEntryType::class,
            'status' => JournalEntryStatus::class,
            'origin' => JournalOrigin::class,
            'occurred_on' => 'date',
            'booked_on' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function reversedEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_entry_id');
    }

    public function reversal(): HasMany
    {
        return $this->hasMany(self::class, 'reverses_entry_id');
    }

    public function relatedEntries(): HasMany
    {
        return $this->hasMany(self::class, 'related_entry_id');
    }

    public function occurrence(): HasOne
    {
        return $this->hasOne(RecurringOccurrence::class);
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /**
     * Entries that still represent an economic event.
     * Voided originals and their reversals stay in the ledger but drop out of reports.
     */
    public function scopeOperational(Builder $query): Builder
    {
        return $query
            ->where('status', JournalEntryStatus::Posted)
            ->whereNull('voided_at')
            ->where('type', '!=', JournalEntryType::Reversal->value);
    }
}
