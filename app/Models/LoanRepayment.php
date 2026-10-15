<?php

namespace App\Models;

use App\Enums\LoanRepaymentKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanRepayment extends Model
{
    protected $fillable = [
        'user_id',
        'account_id',
        'journal_entry_id',
        'kind',
        'amount_minor',
        'occurred_on',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'kind' => LoanRepaymentKind::class,
            'amount_minor' => 'integer',
            'occurred_on' => 'date',
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

    public function allocations(): HasMany
    {
        return $this->hasMany(LoanAllocation::class);
    }
}
