<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstrumentPrice extends Model
{
    protected $fillable = [
        'user_id',
        'instrument_id',
        'priced_on',
        'price_units',
        'source',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'priced_on' => 'date',
            'price_units' => 'integer',
            'fetched_at' => 'datetime',
        ];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
