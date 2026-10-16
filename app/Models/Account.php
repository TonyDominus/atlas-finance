<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Enums\WealthBucket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Account extends Model
{
    protected $fillable = [
        'user_id',
        'institution_id',
        'name',
        'type',
        'currency',
        'color',
        'icon',
        'status',
        'include_in_net_worth',
        'opening_balance_minor',
        'opening_balance_on',
        'notes',
        'sync_provider',
        'wealth_bucket',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'status' => AccountStatus::class,
            'wealth_bucket' => WealthBucket::class,
            'include_in_net_worth' => 'boolean',
            'opening_balance_minor' => 'integer',
            'opening_balance_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function ledgerAccount(): HasOne
    {
        return $this->hasOne(LedgerAccount::class, 'financial_account_id');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(ReconciliationRecord::class);
    }

    public function isPostable(): bool
    {
        return $this->status === AccountStatus::Active;
    }

    public function bucket(): WealthBucket
    {
        return $this->wealth_bucket ?? $this->type->defaultBucket();
    }

    public function isLiquid(): bool
    {
        return $this->bucket() === WealthBucket::Liquid;
    }
}
