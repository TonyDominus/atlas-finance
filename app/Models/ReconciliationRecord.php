<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationRecord extends Model
{
    protected $fillable = [
        'user_id',
        'account_id',
        'reconciled_on',
        'statement_balance_minor',
        'computed_balance_minor',
        'difference_minor',
        'journal_entry_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'reconciled_on' => 'date',
            'statement_balance_minor' => 'integer',
            'computed_balance_minor' => 'integer',
            'difference_minor' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
