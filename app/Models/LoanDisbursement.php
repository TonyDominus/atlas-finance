<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanDisbursement extends Model
{
    protected $fillable = [
        'loan_id',
        'user_id',
        'account_id',
        'journal_entry_id',
        'amount_minor',
        'occurred_on',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'occurred_on' => 'date',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
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
