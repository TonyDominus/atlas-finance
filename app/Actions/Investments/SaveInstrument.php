<?php

declare(strict_types=1);

namespace App\Actions\Investments;

use App\Enums\InstrumentKind;
use App\Models\Instrument;
use App\Models\SecurityTrade;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class SaveInstrument
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $user, array $input, ?Instrument $instrument = null): Instrument
    {
        $symbol = strtoupper(trim((string) $input['symbol']));
        $currency = strtoupper((string) $input['currency']);

        $duplicate = Instrument::query()
            ->where('user_id', $user->id)
            ->where('symbol', $symbol)
            ->when($instrument, fn ($query) => $query->whereKeyNot($instrument->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['symbol' => 'Hai già un titolo con questo simbolo.']);
        }

        if ($instrument && $instrument->currency !== $currency && SecurityTrade::query()->where('instrument_id', $instrument->id)->exists()) {
            throw ValidationException::withMessages(['currency' => 'La valuta non cambia dopo la prima operazione.']);
        }

        $attributes = [
            'symbol' => $symbol,
            'name' => trim((string) $input['name']),
            'isin' => ($isin = strtoupper(trim((string) ($input['isin'] ?? '')))) !== '' ? $isin : null,
            'kind' => InstrumentKind::from((string) $input['kind']),
            'currency' => $currency,
            'exchange' => ($exchange = trim((string) ($input['exchange'] ?? ''))) !== '' ? $exchange : null,
            'quote_symbol' => ($quote = trim((string) ($input['quote_symbol'] ?? ''))) !== '' ? $quote : null,
            'watch' => (bool) ($input['watch'] ?? false),
            'notes' => ($input['notes'] ?? null) ?: null,
        ];

        if ($instrument) {
            if ($instrument->user_id !== $user->id) {
                throw ValidationException::withMessages(['symbol' => 'Titolo non disponibile.']);
            }

            $instrument->fill($attributes)->save();

            return $instrument;
        }

        return Instrument::query()->create(['user_id' => $user->id, ...$attributes]);
    }
}
