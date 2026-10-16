<?php

namespace App\Http\Controllers;

use App\Actions\Investments\RecordPrice;
use App\Actions\Investments\SaveInstrument;
use App\Domain\Investments\Decimal;
use App\Domain\Money\Currency;
use App\Enums\InstrumentKind;
use App\Http\Controllers\Concerns\OwnsFinancialRecords;
use App\Models\Instrument;
use App\Models\InstrumentPrice;
use App\Models\SecurityTrade;
use App\Services\Investments\AlphaVantageQuotes;
use App\Services\Investments\PositionReader;
use App\Services\Investments\PriceSources;
use App\Services\Investments\QuoteUnavailableException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MarketController extends Controller
{
    use OwnsFinancialRecords;

    public function index(Request $request, PositionReader $positions, AlphaVantageQuotes $quotes): Response
    {
        $user = $request->user();
        $held = collect($positions->portfolio($user)['positions'])->groupBy('instrument_id');
        $instruments = Instrument::query()->where('user_id', $user->id)->orderBy('symbol')->get();
        $prices = InstrumentPrice::query()
            ->where('user_id', $user->id)
            ->orderBy('priced_on')
            ->orderBy('updated_at')
            ->get()
            ->groupBy('instrument_id');

        $rows = $instruments->map(function (Instrument $instrument) use ($held, $prices) {
            $daily = $this->daily($prices->get($instrument->id, collect()));
            $last = $daily->last();
            $previous = $daily->count() > 1 ? $daily->get($daily->count() - 2) : null;
            $scale = Currency::scale($instrument->currency);
            $quantity = $held->get($instrument->id, collect())->sum('quantity_units');

            return [
                'id' => $instrument->id,
                'symbol' => $instrument->symbol,
                'name' => $instrument->name,
                'isin' => $instrument->isin,
                'kind' => $instrument->kind->value,
                'kind_label' => $instrument->kind->label(),
                'currency' => $instrument->currency,
                'exchange' => $instrument->exchange,
                'quote_symbol' => $instrument->quote_symbol,
                'watch' => $instrument->watch,
                'notes' => $instrument->notes,
                'held' => $quantity > 0,
                'quantity' => $quantity > 0 ? Decimal::format($quantity) : null,
                'price' => $last ? Decimal::format($last->price_units, $scale) : null,
                'priced_on' => $last?->priced_on->toDateString(),
                'source' => $last ? PriceSources::label($last->source) : null,
                'change_percent' => $last && $previous && $previous->price_units > 0
                    ? round((($last->price_units - $previous->price_units) * 100) / $previous->price_units, 2)
                    : null,
                'previous_on' => $previous?->priced_on->toDateString(),
                'points' => $daily->slice(-120)->map(fn (InstrumentPrice $price) => [
                    'date' => $price->priced_on->toDateString(),
                    'price' => $price->price_units / Decimal::FACTOR,
                ])->values()->all(),
            ];
        });

        return Inertia::render('Market/Index', [
            'instruments' => $rows->all(),
            'kinds' => InstrumentKind::options(),
            'currencies' => Currency::codes(),
            'provider' => [
                'name' => 'Alpha Vantage',
                'configured' => $quotes->configured(),
                'url' => 'https://www.alphavantage.co/documentation/',
            ],
            'today' => now()->toDateString(),
        ]);
    }

    public function store(Request $request, SaveInstrument $action): RedirectResponse
    {
        $action->handle($request->user(), $this->validated($request));

        return redirect()->route('market.index')->with('success', 'Titolo aggiunto.');
    }

    public function update(Request $request, Instrument $instrument, SaveInstrument $action): RedirectResponse
    {
        $this->owned($request, $instrument);
        $action->handle($request->user(), $this->validated($request), $instrument);

        return redirect()->route('market.index')->with('success', 'Titolo aggiornato.');
    }

    public function destroy(Request $request, Instrument $instrument): RedirectResponse
    {
        $this->owned($request, $instrument);

        if (SecurityTrade::query()->where('instrument_id', $instrument->id)->exists()) {
            return back()->with('error', 'Il titolo ha operazioni registrate: toglilo dalla lista di osservazione invece di eliminarlo.');
        }

        $instrument->delete();

        return redirect()->route('market.index')->with('success', 'Titolo eliminato insieme ai suoi prezzi.');
    }

    public function price(Request $request, Instrument $instrument, RecordPrice $action): RedirectResponse
    {
        $this->owned($request, $instrument);
        $data = $request->validate([
            'price' => ['required', 'string', 'max:32'],
            'priced_on' => ['required', 'date'],
        ]);

        $action->manual($request->user(), $instrument, $data['price'], $data['priced_on']);

        return back()->with('success', "Prezzo di {$instrument->symbol} salvato come inserito a mano.");
    }

    public function refresh(Request $request, RecordPrice $action, PositionReader $positions): RedirectResponse
    {
        $user = $request->user();
        $heldIds = collect($positions->portfolio($user)['positions'])->pluck('instrument_id')->all();
        $instruments = Instrument::query()
            ->where('user_id', $user->id)
            ->where(fn ($query) => $query->where('watch', true)->orWhereIn('id', $heldIds ?: [0]))
            ->orderBy('symbol')
            ->get();

        if ($instruments->isEmpty()) {
            return back()->with('error', 'Nessun titolo in portafoglio o in osservazione da aggiornare.');
        }

        $result = $action->refresh($user, $instruments);

        if ($result['updated'] === []) {
            return back()->with('error', 'Nessuna quotazione aggiornata. '.implode(' ', array_unique($result['failed'])));
        }

        $message = 'Aggiornati: '.implode(', ', $result['updated']).'.';

        if ($result['failed'] !== []) {
            $message .= ' Non disponibili: '.implode(', ', array_keys($result['failed'])).'.';
        }

        return back()->with('success', $message);
    }

    public function history(Request $request, Instrument $instrument, RecordPrice $action): RedirectResponse
    {
        $this->owned($request, $instrument);

        try {
            $count = $action->history($request->user(), $instrument);
        } catch (QuoteUnavailableException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "Storico di {$instrument->symbol}: {$count} chiusure giornaliere da Alpha Vantage.");
    }

    /**
     * One price per day: when two sources disagree on a day, the most recently saved wins.
     *
     * @param  Collection<int, InstrumentPrice>  $prices
     * @return Collection<int, InstrumentPrice>
     */
    private function daily(Collection $prices): Collection
    {
        return $prices
            ->groupBy(fn (InstrumentPrice $price) => $price->priced_on->toDateString())
            ->map(fn (Collection $day) => $day->sortBy('updated_at')->last())
            ->sortKeys()
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'symbol' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9.\-_:]+$/'],
            'name' => ['required', 'string', 'max:120'],
            'isin' => ['nullable', 'string', 'size:12', 'regex:/^[A-Za-z]{2}[A-Za-z0-9]{9}[0-9]$/'],
            'kind' => ['required', Rule::enum(InstrumentKind::class)],
            'currency' => ['required', Rule::in(Currency::codes())],
            'exchange' => ['nullable', 'string', 'max:32'],
            'quote_symbol' => ['nullable', 'string', 'max:32'],
            'watch' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'symbol.regex' => 'Il simbolo può contenere lettere, numeri, punto, trattino e due punti.',
            'isin.size' => 'Un ISIN ha 12 caratteri.',
            'isin.regex' => 'ISIN non valido.',
        ]);
    }
}
