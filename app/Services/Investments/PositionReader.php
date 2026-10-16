<?php

declare(strict_types=1);

namespace App\Services\Investments;

use App\Actions\Books\ProvisionUserBooks;
use App\Domain\Investments\Decimal;
use App\Domain\Money\Currency;
use App\Domain\Money\Money;
use App\Enums\TradeSide;
use App\Models\Instrument;
use App\Models\InstrumentPrice;
use App\Models\SecurityTrade;
use App\Models\User;
use App\Services\Ledger\BalanceReader;
use Illuminate\Support\Collection;

/**
 * Positions are rebuilt from posted trades every time. Nothing here is stored.
 */
final class PositionReader
{
    public function __construct(
        private BalanceReader $balances,
        private ProvisionUserBooks $books,
    ) {}

    /**
     * @return array{quantity_units: int, cost_minor: int, last_traded_on: string|null}
     */
    public function position(User $user, int $instrumentId, int $accountId): array
    {
        $trades = SecurityTrade::query()
            ->where('user_id', $user->id)
            ->where('instrument_id', $instrumentId)
            ->where('account_id', $accountId)
            ->active()
            ->orderBy('traded_on')
            ->orderBy('id')
            ->get();

        return $this->fold($trades);
    }

    /**
     * @return array{positions: list<array<string, mixed>>, by_currency: list<array<string, mixed>>, trades: list<array<string, mixed>>}
     */
    public function portfolio(User $user): array
    {
        $trades = SecurityTrade::query()
            ->where('user_id', $user->id)
            ->active()
            ->with(['instrument', 'account'])
            ->orderBy('traded_on')
            ->orderBy('id')
            ->get();

        $latestPrices = $this->latestPrices($user);
        $positions = [];

        foreach ($trades->groupBy(fn (SecurityTrade $trade) => $trade->instrument_id.'-'.$trade->account_id) as $group) {
            /** @var SecurityTrade $first */
            $first = $group->first();
            $state = $this->fold($group);

            if ($state['quantity_units'] === 0) {
                continue;
            }

            $positions[] = $this->present($first->instrument, $first->account->name, $first->account_id, $state, $latestPrices[$first->instrument_id] ?? null);
        }

        usort($positions, fn (array $a, array $b) => [$a['currency'], $a['symbol']] <=> [$b['currency'], $b['symbol']]);

        return [
            'positions' => $positions,
            'by_currency' => $this->totals($user, $positions),
            'trades' => $trades->sortByDesc(fn (SecurityTrade $trade) => sprintf('%s-%012d', $trade->traded_on->toDateString(), $trade->id))
                ->take(30)
                ->map(fn (SecurityTrade $trade) => $this->trade($trade, $trades))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<int, InstrumentPrice>
     */
    public function latestPrices(User $user): array
    {
        $prices = InstrumentPrice::query()
            ->where('user_id', $user->id)
            ->orderBy('priced_on')
            ->orderBy('updated_at')
            ->orderBy('id')
            ->get();

        $latest = [];

        foreach ($prices as $price) {
            $latest[$price->instrument_id] = $price;
        }

        return $latest;
    }

    /**
     * @param  Collection<int, SecurityTrade>  $trades
     * @return array{quantity_units: int, cost_minor: int, last_traded_on: string|null}
     */
    private function fold(Collection $trades): array
    {
        $quantity = 0;
        $cost = 0;
        $last = null;

        foreach ($trades as $trade) {
            if ($trade->side === TradeSide::Buy) {
                $quantity += $trade->quantity_units;
                $cost += $trade->gross_minor;
            } else {
                $quantity -= $trade->quantity_units;
                $cost -= $trade->cost_minor;
            }

            $last = $trade->traded_on->toDateString();
        }

        return ['quantity_units' => $quantity, 'cost_minor' => $quantity === 0 ? 0 : $cost, 'last_traded_on' => $last];
    }

    /**
     * @param  array{quantity_units: int, cost_minor: int, last_traded_on: string|null}  $state
     * @return array<string, mixed>
     */
    private function present(Instrument $instrument, string $accountName, int $accountId, array $state, ?InstrumentPrice $price): array
    {
        $currency = $instrument->currency;
        $scale = Currency::scale($currency);
        $value = $price ? Decimal::valueMinor($state['quantity_units'], $price->price_units, $scale) : null;
        $average = Decimal::unitPrice($state['cost_minor'], $state['quantity_units'], $scale);

        return [
            'instrument_id' => $instrument->id,
            'symbol' => $instrument->symbol,
            'name' => $instrument->name,
            'kind_label' => $instrument->kind->label(),
            'account_id' => $accountId,
            'account' => $accountName,
            'currency' => $currency,
            'quantity' => Decimal::format($state['quantity_units']),
            'quantity_units' => $state['quantity_units'],
            'cost_minor' => $state['cost_minor'],
            'cost' => Money::ofMinor($state['cost_minor'], $currency)->format(),
            'average_price' => Decimal::format($average, $scale),
            'price' => $price ? Decimal::format($price->price_units, $scale) : null,
            'priced_on' => $price?->priced_on->toDateString(),
            'price_source' => $price ? PriceSources::label($price->source) : null,
            'value_minor' => $value,
            'value' => $value === null ? null : Money::ofMinor($value, $currency)->format(),
            'unrealized_minor' => $value === null ? null : $value - $state['cost_minor'],
            'unrealized' => $value === null ? null : Money::ofMinor($value - $state['cost_minor'], $currency)->format(),
            'unrealized_percent' => $value === null || $state['cost_minor'] === 0
                ? null
                : round((($value - $state['cost_minor']) * 100) / $state['cost_minor'], 2),
            'last_traded_on' => $state['last_traded_on'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $positions
     * @return list<array<string, mixed>>
     */
    private function totals(User $user, array $positions): array
    {
        $currencies = collect($positions)->pluck('currency')
            ->merge($user->ledgerAccounts()->where('system_key', 'investments')->pluck('currency'))
            ->unique()
            ->values();

        $rows = [];

        foreach ($currencies as $currency) {
            $mine = array_values(array_filter($positions, fn (array $row) => $row['currency'] === $currency));
            $cost = array_sum(array_column($mine, 'cost_minor'));
            $priced = array_filter($mine, fn (array $row) => $row['value_minor'] !== null);
            $pricedCost = array_sum(array_column($priced, 'cost_minor'));
            $pricedValue = array_sum(array_column($priced, 'value_minor'));
            $ledger = $this->balances->ledgerMinor($this->books->system($user, 'investments', $currency));
            $realized = $this->balances->ledgerMinor($this->books->system($user, 'investment_results', $currency));
            $unassigned = $ledger - $cost;
            $value = $pricedValue + ($cost - $pricedCost) + $unassigned;

            if ($mine === [] && $ledger === 0 && $realized === 0) {
                continue;
            }

            $rows[] = [
                'currency' => $currency,
                'cost_minor' => $cost,
                'cost' => Money::ofMinor($cost, $currency)->format(),
                'ledger_minor' => $ledger,
                'ledger' => Money::ofMinor($ledger, $currency)->format(),
                'unassigned_minor' => $unassigned,
                'unassigned' => Money::ofMinor($unassigned, $currency)->format(),
                'priced_count' => count($priced),
                'unpriced_count' => count($mine) - count($priced),
                'value_minor' => $value,
                'value' => Money::ofMinor($value, $currency)->format(),
                'unrealized_minor' => $pricedValue - $pricedCost,
                'unrealized' => Money::ofMinor($pricedValue - $pricedCost, $currency)->format(),
                'realized_minor' => $realized,
                'realized' => Money::ofMinor($realized, $currency)->format(),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, SecurityTrade>  $all
     * @return array<string, mixed>
     */
    private function trade(SecurityTrade $trade, Collection $all): array
    {
        $scale = Currency::scale($trade->currency);
        $latest = $all
            ->filter(fn (SecurityTrade $other) => $other->instrument_id === $trade->instrument_id && $other->account_id === $trade->account_id)
            ->last();

        return [
            'id' => $trade->id,
            'side' => $trade->side->value,
            'side_label' => $trade->side->label(),
            'symbol' => $trade->instrument->symbol,
            'account' => $trade->account->name,
            'traded_on' => $trade->traded_on->toDateString(),
            'quantity' => Decimal::format($trade->quantity_units),
            'unit_price' => Decimal::format(Decimal::unitPrice($trade->gross_minor, $trade->quantity_units, $scale), $scale),
            'gross' => Money::ofMinor($trade->gross_minor, $trade->currency)->format(),
            'fee' => $trade->fee_minor > 0 ? Money::ofMinor($trade->fee_minor, $trade->currency)->format() : null,
            'result' => $trade->side === TradeSide::Sell
                ? Money::ofMinor($trade->gross_minor - $trade->cost_minor, $trade->currency)->format()
                : null,
            'movement_id' => $trade->journal_entry_id,
            'can_void' => $latest?->id === $trade->id,
        ];
    }
}
