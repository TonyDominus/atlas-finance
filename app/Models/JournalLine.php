<?php

namespace App\Models;

use App\Enums\JournalSide;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    protected $fillable = [
        'journal_entry_id',
        'ledger_account_id',
        'side',
        'amount_minor',
        'currency',
        'memo',
    ];

    protected function casts(): array
    {
        return [
            'side' => JournalSide::class,
            'amount_minor' => 'integer',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }
}
