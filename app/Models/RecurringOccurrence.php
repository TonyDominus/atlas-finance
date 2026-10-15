<?php

namespace App\Models;

use App\Enums\OccurrenceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringOccurrence extends Model
{
    protected $fillable = [
        'user_id',
        'recurring_rule_id',
        'due_on',
        'amount_minor',
        'currency',
        'status',
        'amount_locked',
        'journal_entry_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'amount_minor' => 'integer',
            'status' => OccurrenceStatus::class,
            'amount_locked' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(RecurringRule::class, 'recurring_rule_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
