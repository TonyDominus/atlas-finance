<?php

namespace App\Models;

use App\Enums\LedgerAccountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerAccount extends Model
{
    protected $fillable = [
        'user_id',
        'financial_account_id',
        'code',
        'name',
        'type',
        'system_key',
        'currency',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'type' => LedgerAccountType::class,
            'is_system' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'financial_account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
