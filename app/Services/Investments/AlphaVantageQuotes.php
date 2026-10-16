<?php

declare(strict_types=1);

namespace App\Services\Investments;

use App\Domain\Investments\Decimal;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Daily quotes from the documented Alpha Vantage API (https://www.alphavantage.co/documentation/).
 * The free key allows a small number of calls per day, so nothing is fetched without a key.
 * Prices are stored as returned: Alpha Vantage does not state the currency, the instrument does.
 */
final class AlphaVantageQuotes
{
    public function configured(): bool
    {
        return $this->key() !== '';
    }

    /**
     * @return array{priced_on: string, price_units: int, previous_close_units: int|null}
     */
    public function latest(string $symbol): array
    {
        $payload = $this->call(['function' => 'GLOBAL_QUOTE', 'symbol' => $symbol]);
        $quote = $payload['Global Quote'] ?? null;

        if (! is_array($quote) || ! isset($quote['05. price'], $quote['07. latest trading day'])) {
            throw new QuoteUnavailableException("Nessuna quotazione per {$symbol}. Controlla il simbolo usato dal fornitore.");
        }

        return [
            'priced_on' => (string) $quote['07. latest trading day'],
            'price_units' => $this->units((string) $quote['05. price'], $symbol),
            'previous_close_units' => isset($quote['08. previous close'])
                ? $this->units((string) $quote['08. previous close'], $symbol)
                : null,
        ];
    }

    /**
     * Roughly the last 100 trading days of closing prices.
     *
     * @return list<array{priced_on: string, price_units: int}>
     */
    public function history(string $symbol): array
    {
        $payload = $this->call(['function' => 'TIME_SERIES_DAILY', 'symbol' => $symbol, 'outputsize' => 'compact']);
        $series = $payload['Time Series (Daily)'] ?? null;

        if (! is_array($series) || $series === []) {
            throw new QuoteUnavailableException("Nessuno storico per {$symbol}. Controlla il simbolo usato dal fornitore.");
        }

        $rows = [];

        foreach ($series as $date => $values) {
            if (! isset($values['4. close'])) {
                continue;
            }

            $rows[] = ['priced_on' => (string) $date, 'price_units' => $this->units((string) $values['4. close'], $symbol)];
        }

        usort($rows, fn (array $a, array $b) => $a['priced_on'] <=> $b['priced_on']);

        return $rows;
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    private function call(array $query): array
    {
        if (! $this->configured()) {
            throw new QuoteUnavailableException('Fornitore non configurato: imposta ALPHAVANTAGE_KEY nel file .env.');
        }

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->get((string) config('services.alphavantage.url'), [...$query, 'apikey' => $this->key()]);
        } catch (ConnectionException) {
            throw new QuoteUnavailableException('Alpha Vantage non raggiungibile in questo momento.');
        }

        if (! $response->successful()) {
            throw new QuoteUnavailableException('Alpha Vantage ha risposto con errore '.$response->status().'.');
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new QuoteUnavailableException('Risposta di Alpha Vantage non leggibile.');
        }

        foreach (['Error Message', 'Note', 'Information'] as $key) {
            if (isset($payload[$key])) {
                throw new QuoteUnavailableException('Alpha Vantage: '.$payload[$key]);
            }
        }

        return $payload;
    }

    private function units(string $value, string $symbol): int
    {
        $trimmed = rtrim(rtrim(trim($value), '0'), '.');
        [$whole, $fraction] = array_pad(explode('.', $trimmed, 2), 2, '');

        try {
            return Decimal::parse($whole.($fraction !== '' ? '.'.substr($fraction, 0, Decimal::SCALE) : ''), 'Prezzo');
        } catch (InvalidArgumentException) {
            throw new QuoteUnavailableException("Prezzo non valido ricevuto per {$symbol}.");
        }
    }

    private function key(): string
    {
        return trim((string) config('services.alphavantage.key'));
    }
}
