<?php

declare(strict_types=1);

namespace App\Actions\Investments;

use App\Domain\Investments\Decimal;
use App\Models\Instrument;
use App\Models\InstrumentPrice;
use App\Models\User;
use App\Services\Investments\AlphaVantageQuotes;
use App\Services\Investments\PriceSources;
use App\Services\Investments\QuoteUnavailableException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class RecordPrice
{
    public function __construct(private AlphaVantageQuotes $quotes) {}

    public function manual(User $user, Instrument $instrument, string $price, string $pricedOn): InstrumentPrice
    {
        $this->owned($user, $instrument);

        try {
            $units = Decimal::parse($price, 'Prezzo');
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['price' => $exception->getMessage()]);
        }

        if ($units <= 0) {
            throw ValidationException::withMessages(['price' => 'Il prezzo deve essere maggiore di zero.']);
        }

        if ($pricedOn > now()->toDateString()) {
            throw ValidationException::withMessages(['priced_on' => 'Un prezzo non può avere una data futura.']);
        }

        return $this->store($user, $instrument, $pricedOn, $units, PriceSources::MANUAL);
    }

    /**
     * @return array{updated: list<string>, failed: array<string, string>}
     */
    public function refresh(User $user, iterable $instruments): array
    {
        $updated = [];
        $failed = [];

        foreach ($instruments as $instrument) {
            $this->owned($user, $instrument);

            try {
                $quote = $this->quotes->latest($instrument->providerSymbol());
                $this->store($user, $instrument, $quote['priced_on'], $quote['price_units'], PriceSources::ALPHA_VANTAGE);
                $updated[] = $instrument->symbol;
            } catch (QuoteUnavailableException $exception) {
                $failed[$instrument->symbol] = $exception->getMessage();

                if (! $this->quotes->configured()) {
                    break;
                }
            }
        }

        return ['updated' => $updated, 'failed' => $failed];
    }

    public function history(User $user, Instrument $instrument): int
    {
        $this->owned($user, $instrument);
        $rows = $this->quotes->history($instrument->providerSymbol());

        foreach ($rows as $row) {
            $this->store($user, $instrument, $row['priced_on'], $row['price_units'], PriceSources::ALPHA_VANTAGE);
        }

        return count($rows);
    }

    private function store(User $user, Instrument $instrument, string $pricedOn, int $units, string $source): InstrumentPrice
    {
        $existing = InstrumentPrice::query()
            ->where('instrument_id', $instrument->id)
            ->where('source', $source)
            ->whereDate('priced_on', $pricedOn)
            ->first();

        $attributes = [
            'price_units' => $units,
            'fetched_at' => $source === PriceSources::MANUAL ? null : now(),
        ];

        if ($existing) {
            $existing->fill($attributes)->save();

            return $existing;
        }

        return InstrumentPrice::query()->create([
            'user_id' => $user->id,
            'instrument_id' => $instrument->id,
            'priced_on' => $pricedOn,
            'source' => $source,
            ...$attributes,
        ]);
    }

    private function owned(User $user, Instrument $instrument): void
    {
        if ($instrument->user_id !== $user->id) {
            throw ValidationException::withMessages(['instrument_id' => 'Titolo non disponibile.']);
        }
    }
}
