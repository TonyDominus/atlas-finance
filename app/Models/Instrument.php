<?php

namespace App\Models;

use App\Enums\InstrumentKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instrument extends Model
{
    protected $fillable = [
        'user_id',
        'symbol',
        'name',
        'isin',
        'kind',
        'currency',
        'exchange',
        'quote_symbol',
        'watch',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'kind' => InstrumentKind::class,
            'watch' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trades(): HasMany
    {
        return $this->hasMany(SecurityTrade::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(InstrumentPrice::class);
    }

    public function providerSymbol(): string
    {
        return $this->quote_symbol ?: $this->symbol;
    }
}
