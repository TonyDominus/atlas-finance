<?php

declare(strict_types=1);

namespace App\Services\Forecast;

use App\Domain\Money\Money;
use App\Enums\AccountStatus;
use App\Enums\JournalEntryStatus;
use App\Enums\JournalSide;
use App\Enums\OccurrenceStatus;
use App\Enums\RecurrenceKind;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\RecurringOccurrence;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class CashFlowForecast
{
    /**
     * Projects posted balances forward. Open loans are never assumed to be repaid.
     *
     * @return array<string, mixed>
     */
    public function project(User $user, string $until, ?string $from = null): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $start = $from ? CarbonImmutable::parse($from)->startOfDay() : $today;
        $end = CarbonImmutable::parse($until)->startOfDay();

        if ($start->lessThan($today)) {
            $start = $today;
        }

        if ($end->greaterThan($today->addYear())) {
            $end = $today->addYear();
        }

        if ($end->lessThan($start)) {
            $end = $start;
        }

        $accounts = Account::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', AccountStatus::Archived)
            ->where('include_in_net_worth', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (Account $account) => $account->isLiquid())
            ->values();

        $balances = $this->openingBalances($user->id, $accounts->pluck('id')->all());
        $events = $this->events($user, $accounts->keyBy('id'), $today->toDateString(), $end->toDateString());
        $byDay = [];

        foreach ($events as $event) {
            $byDay[$event['date']][] = $event;
        }

        $currencies = $accounts->pluck('currency')->unique()->values();
        $result = [];

        foreach ($currencies as $currency) {
            $ids = $accounts->where('currency', $currency)->pluck('id')->all();
            $state = [];

            foreach ($ids as $id) {
                $state[$id] = $balances[$id] ?? 0;
            }

            $cursor = $today;
            $opening = $this->total($state);
            $minimum = $opening;
            $minimumOn = $today->toDateString();
            $negative = [];
            $openNegative = null;
            $points = [];
            $visibleEvents = [];

            while ($cursor->lessThanOrEqualTo($end)) {
                foreach ($byDay[$cursor->toDateString()] ?? [] as $event) {
                    if (! array_key_exists($event['account_id'], $state)) {
                        continue;
                    }

                    $state[$event['account_id']] += $event['delta'];

                    if ($cursor->greaterThanOrEqualTo($start)) {
                        $visibleEvents[] = $event;
                    }
                }

                $total = $this->total($state);

                if ($cursor->greaterThanOrEqualTo($start)) {
                    if ($total < $minimum) {
                        $minimum = $total;
                        $minimumOn = $cursor->toDateString();
                    }

                    if ($total < 0 && $openNegative === null) {
                        $openNegative = $cursor->toDateString();
                    }

                    if ($total >= 0 && $openNegative !== null) {
                        $negative[] = ['from' => $openNegative, 'until' => $cursor->subDay()->toDateString()];
                        $openNegative = null;
                    }

                    $points[] = ['date' => $cursor->toDateString(), 'minor' => $total];
                }

                $cursor = $cursor->addDay();
            }

            if ($openNegative !== null) {
                $negative[] = ['from' => $openNegative, 'until' => $end->toDateString()];
            }

            $accountRows = [];

            foreach ($ids as $id) {
                /** @var Account $account */
                $account = $accounts->firstWhere('id', $id);
                $closing = $state[$id];
                $accountRows[] = [
                    'id' => $id,
                    'name' => $account->name,
                    'opening_minor' => $balances[$id] ?? 0,
                    'closing_minor' => $closing,
                    'opening' => Money::ofMinor($balances[$id] ?? 0, $currency)->format(),
                    'closing' => Money::ofMinor($closing, $currency)->format(),
                ];
            }

            $sampled = $this->sample($points);

            $result[] = [
                'currency' => $currency,
                'opening_minor' => $opening,
                'opening' => Money::ofMinor($opening, $currency)->format(),
                'closing_minor' => $this->total($state),
                'closing' => Money::ofMinor($this->total($state), $currency)->format(),
                'minimum_minor' => $minimum,
                'minimum' => Money::ofMinor($minimum, $currency)->format(),
                'minimum_on' => $minimumOn,
                'negative_ranges' => $negative,
                'accounts' => $accountRows,
                'points' => $sampled,
                'events' => array_slice($visibleEvents, 0, 180),
            ];
        }

        return [
            'estimate' => true,
            'from' => $start->toDateString(),
            'until' => $end->toDateString(),
            'note' => 'Stima. I prestiti aperti non sono considerati rimborsati alla scadenza.',
            'currencies' => $result,
        ];
    }

    /**
     * @param  list<int>  $accountIds
     * @return array<int, int>
     */
    private function openingBalances(int $userId, array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        $rows = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->join('ledger_accounts as la', 'la.id', '=', 'jl.ledger_account_id')
            ->where('je.user_id', $userId)
            ->where('je.status', JournalEntryStatus::Posted->value)
            ->whereIn('la.financial_account_id', $accountIds)
            ->groupBy('la.financial_account_id')
            ->select('la.financial_account_id as account_id')
            ->selectRaw('SUM(CASE WHEN jl.side = ? THEN jl.amount_minor ELSE -jl.amount_minor END) as balance_minor', [JournalSide::Debit->value])
            ->get();

        $map = array_fill_keys($accountIds, 0);

        foreach ($rows as $row) {
            $map[(int) $row->account_id] = (int) $row->balance_minor;
        }

        return $map;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Account>  $accounts
     * @return list<array{date: string, account_id: int, delta: int, description: string, source: string, kind: string, amount: string}>
     */
    private function events($user, $accounts, string $from, string $until): array
    {
        $events = [];
        $occurrences = RecurringOccurrence::query()
            ->where('user_id', $user->id)
            ->where('status', OccurrenceStatus::Forecast)
            ->whereDate('due_on', '>=', $from)
            ->whereDate('due_on', '<=', $until)
            ->with('rule')
            ->orderBy('due_on')
            ->get();

        foreach ($occurrences as $occurrence) {
            $rule = $occurrence->rule;

            if (! $rule || ! $accounts->has($rule->account_id)) {
                continue;
            }

            $delta = $rule->kind === RecurrenceKind::Income ? $occurrence->amount_minor : -$occurrence->amount_minor;

            if ($rule->kind === RecurrenceKind::Transfer) {
                $events[] = $this->event($occurrence->due_on->toDateString(), $rule->account_id, -$occurrence->amount_minor, $rule->description, 'recurrence', $rule->kind->value, $occurrence->currency);

                if ($rule->destination_account_id && $accounts->has($rule->destination_account_id)) {
                    $events[] = $this->event($occurrence->due_on->toDateString(), $rule->destination_account_id, $occurrence->amount_minor, $rule->description, 'recurrence', $rule->kind->value, $occurrence->currency);
                }

                continue;
            }

            $events[] = $this->event($occurrence->due_on->toDateString(), $rule->account_id, $delta, $rule->description, 'recurrence', $rule->kind->value, $occurrence->currency);
        }

        $forecasts = JournalEntry::query()
            ->where('user_id', $user->id)
            ->where('status', JournalEntryStatus::Forecast)
            ->whereNull('voided_at')
            ->whereDate('booked_on', '>=', $from)
            ->whereDate('booked_on', '<=', $until)
            ->with('lines.ledgerAccount')
            ->orderBy('booked_on')
            ->get();

        foreach ($forecasts as $entry) {
            foreach ($entry->lines as $line) {
                $accountId = $line->ledgerAccount?->financial_account_id;

                if ($accountId === null || ! $accounts->has($accountId)) {
                    continue;
                }

                $delta = $line->side === JournalSide::Debit ? $line->amount_minor : -$line->amount_minor;
                $events[] = $this->event($entry->booked_on->toDateString(), $accountId, $delta, $entry->description, 'movement', $entry->type->value, $entry->currency);
            }
        }

        usort($events, fn (array $left, array $right) => [$left['date'], $left['description']] <=> [$right['date'], $right['description']]);

        return $events;
    }

    /**
     * @return array{date: string, account_id: int, delta: int, description: string, source: string, kind: string, amount: string}
     */
    private function event(string $date, int $accountId, int $delta, string $description, string $source, string $kind, string $currency): array
    {
        return [
            'date' => $date,
            'account_id' => $accountId,
            'delta' => $delta,
            'description' => $description,
            'source' => $source,
            'kind' => $kind,
            'amount' => Money::ofMinor(abs($delta), $currency)->format(),
        ];
    }

    /**
     * @param  array<int, int>  $state
     */
    private function total(array $state): int
    {
        return array_sum($state);
    }

    /**
     * @param  list<array{date: string, minor: int}>  $points
     * @return list<array{date: string, minor: int}>
     */
    private function sample(array $points): array
    {
        if (count($points) <= 120) {
            return $points;
        }

        $sampled = [];

        foreach ($points as $index => $point) {
            if ($index % 7 === 0 || $index === count($points) - 1) {
                $sampled[] = $point;
            }
        }

        return $sampled;
    }
}
