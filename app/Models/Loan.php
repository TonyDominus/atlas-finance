<?php

namespace App\Models;

use App\Enums\LoanDirection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    protected $fillable = [
        'user_id',
        'counterparty_id',
        'direction',
        'currency',
        'opened_on',
        'due_on',
        'description',
        'notes',
        'agreed_interest_minor',
    ];

    protected function casts(): array
    {
        return [
            'direction' => LoanDirection::class,
            'opened_on' => 'date',
            'due_on' => 'date',
            'agreed_interest_minor' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(LoanDisbursement::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LoanAllocation::class);
    }
}
