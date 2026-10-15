<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanAllocation extends Model
{
    protected $fillable = [
        'loan_repayment_id',
        'loan_id',
        'principal_minor',
        'interest_minor',
    ];

    protected function casts(): array
    {
        return [
            'principal_minor' => 'integer',
            'interest_minor' => 'integer',
        ];
    }

    public function repayment(): BelongsTo
    {
        return $this->belongsTo(LoanRepayment::class, 'loan_repayment_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
