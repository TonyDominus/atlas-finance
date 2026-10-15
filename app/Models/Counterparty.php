<?php

namespace App\Models;

use App\Enums\CounterpartyType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Counterparty extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => CounterpartyType::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }
}
