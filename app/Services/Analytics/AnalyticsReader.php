<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Domain\Money\Money;
use App\Enums\AccountStatus;
use App\Enums\JournalEntryStatus;
use App\Enums\JournalEntryType;
use App\Enums\JournalSide;
use App\Enums\RecurrencePurpose;
use App\Enums\RecurrenceStatus;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\RecurringRule;
use App\Models\User;
use App\Services\Budget\BudgetReader;
use App\Services\Forecast\CashFlowForecast;
use App\Services\Ledger\BalanceReader;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class AnalyticsReader
{
    public function __construct(
        private BalanceReader $balances,
        private CashFlowForecast $forecast,
        private BudgetReader $budgets,
    ) {}

    /**
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    public function report(User $user, array $filters): array
    {
        $from = $filters['from'] !== '' ? $filters['from'] : now()->subMonthsNoOverflow(5)->startOfMonth()->toDateString();
        $to = $filters['to'] !== '' ? $filters['to'] : now()->toDateString();
        $currency = 'EUR';
        $entries = $this->entries($user, $from, $to, $filters);
        $months = $this->months($from, $to);
        $income = array_fill_keys($months, 0);
        $expense = array_fill_keys($months, 0);
        $categories = [];

        foreach ($entries as $entry) {
            $month = $entry->booked_on->format('Y-m');

            if (! array_key_exists($month, $income)) {
                continue;
            }

            $amount = (int) ($entry->lines->first()->amount_minor ?? 0);

            if ($entry->type === JournalEntryType::Income || $entry->type === JournalEntryType::Interest) {
                $income[$month] += $amount;
            }

            if (in_array($entry->type, [JournalEntryType::Expense, JournalEntryType::Fee], true)) {
                $expense[$month] += $amount;
                $name = $entry->category?->name ?? 'Senza categoria';
                $categories[$name] = ($categories[$name] ?? 0) + $amount;
            }

            if ($entry->type === JournalEntryType::Refund) {
                $expense[$month] -= $amount;
                $name = $entry->category?->name ?? 'Senza categoria';
                $categories[$name] = ($categories[$name] ?? 0) - $amount;
            }

            if ($entry->type === JournalEntryType::LoanRepayment) {
                foreach ($entry->lines as $line) {
                    if ($line->ledgerAccount?->system_key === 'interest_income' && $line->side === JournalSide::Credit) {
                        $income[$month] += $line->amount_minor;
                    }

                    if ($line->ledgerAccount?->system_key === 'expense' && $line->side === JournalSide::Debit) {
                        $expense[$month] += $line->amount_minor;
                        $categories['Interessi passivi'] = ($categories['Interessi passivi'] ?? 0) + $line->amount_minor;
                    }
                }
            }
        }

        arsort($categories);
        $categories = array_filter($categories, fn (int $value) => $value > 0);

        $liquidity = $this->series($user, $months, $filters['account_id'] ?? '', true);
        $worth = $this->series($user, $months, $filters['account_id'] ?? '', false);
        $accounts = Account::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', AccountStatus::Archived)
            ->orderBy('name')
            ->get();
        $map = $this->balances->accountBalanceMap($user->id);
        $horizon = CarbonImmutable::parse($to)->addDays(90)->toDateString();
        $projection = $this->forecast->project($user, $horizon, now()->toDateString());

        return [
            'filters' => [
                'from' => $from,
                'to' => $to,
                'account_id' => $filters['account_id'] ?? '',
                'category_id' => $filters['category_id'] ?? '',
                'counterparty_id' => $filters['counterparty_id'] ?? '',
                'type' => $filters['type'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'currency' => $currency,
            'income_expense' => [
                'labels' => array_values($months),
                'income' => array_values($income),
                'expense' => array_values($expense),
            ],
            'savings' => [
                'labels' => array_values($months),
                'values' => array_map(fn (string $month) => $income[$month] - $expense[$month], $months),
            ],
            'categories' => [
                'labels' => array_keys($categories),
                'values' => array_values($categories),
            ],
            'liquidity' => $liquidity,
            'net_worth' => $worth,
            'accounts' => [
                'labels' => $accounts->pluck('name')->all(),
                'values' => $accounts->map(fn (Account $account) => $map[$account->id] ?? 0)->all(),
                'colors' => $accounts->pluck('color')->all(),
            ],
            'bank_fees' => $this->bankFees($user),
            'forecast' => $projection,
            'actuals_note' => 'I grafici di entrate, spese e categorie usano solo movimenti contabilizzati. La proiezione è una stima separata.',
        ];
    }

    /**
     * @param  array<string, string>  $filters
     * @return \Illuminate\Support\Collection<int, JournalEntry>
     */
    private function entries(User $user, string $from, string $to, array $filters)
    {
        return JournalEntry::query()
            ->where('user_id', $user->id)
            ->whereNull('voided_at')
            ->where('type', '!=', JournalEntryType::Reversal->value)
            ->where('currency', 'EUR')
            ->when(($filters['status'] ?? '') !== '', fn ($query) => $query->where('status', $filters['status']))
            ->when(($filters['status'] ?? '') === '', fn ($query) => $query->where('status', JournalEntryStatus::Posted->value))
            ->when(($filters['type'] ?? '') !== '', fn ($query) => $query->where('type', $filters['type']))
            ->when(($filters['category_id'] ?? '') !== '', fn ($query) => $query->where('category_id', $filters['category_id']))
            ->when(($filters['counterparty_id'] ?? '') !== '', fn ($query) => $query->where('counterparty_id', $filters['counterparty_id']))
            ->when(($filters['account_id'] ?? '') !== '', function ($query) use ($filters) {
                $query->whereHas('lines.ledgerAccount', fn ($ledger) => $ledger->where('financial_account_id', $filters['account_id']));
            })
            ->whereBetween('booked_on', [$from, $to])
            ->with(['lines.ledgerAccount', 'category'])
            ->get();
    }

    /**
     * @return list<string>
     */
    private function months(string $from, string $to): array
    {
        $cursor = CarbonImmutable::parse($from)->startOfMonth();
        $end = CarbonImmutable::parse($to)->startOfMonth();
        $months = [];

        while ($cursor->lessThanOrEqualTo($end) && count($months) < 24) {
            $months[] = $cursor->format('Y-m');
            $cursor = $cursor->addMonthNoOverflow();
        }

        return $months;
    }

    /**
     * @param  list<string>  $months
     * @return array{labels: list<string>, values: list<int>}
     */
    private function series(User $user, array $months, string $accountId, bool $liquidOnly): array
    {
        $accounts = Account::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', AccountStatus::Archived)
            ->where('include_in_net_worth', true)
            ->when($accountId !== '', fn ($query) => $query->whereKey($accountId))
            ->get()
            ->when($liquidOnly && $accountId === '', fn ($accounts) => $accounts->filter(fn (Account $account) => $account->isLiquid())->values());

        $lines = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->join('ledger_accounts as la', 'la.id', '=', 'jl.ledger_account_id')
            ->where('je.user_id', $user->id)
            ->where('je.status', JournalEntryStatus::Posted->value)
            ->where('je.currency', 'EUR')
            ->whereIn('la.financial_account_id', $accounts->pluck('id')->all() ?: [0])
            ->orderBy('je.booked_on')
            ->get(['la.financial_account_id as account_id', 'je.booked_on', 'jl.side', 'jl.amount_minor']);

        $running = array_fill_keys($accounts->pluck('id')->all(), 0);
        $index = 0;
        $values = [];
        $lineCount = $lines->count();

        foreach ($months as $month) {
            $limit = CarbonImmutable::parse($month.'-01')->endOfMonth()->toDateString();

            while ($index < $lineCount && $lines[$index]->booked_on <= $limit) {
                $id = (int) $lines[$index]->account_id;
                $amount = (int) $lines[$index]->amount_minor;
                $running[$id] = ($running[$id] ?? 0) + ($lines[$index]->side === JournalSide::Debit->value ? $amount : -$amount);
                $index++;
            }

            $total = array_sum($running);

            if (! $liquidOnly && $accountId === '') {
                $asOf = $limit > now()->toDateString() ? now()->toDateString() : $limit;
                $total += $this->systemAsOf($user, 'investments', $asOf);
                $total += $this->systemAsOf($user, 'loans_receivable', $asOf);
                $total -= $this->systemAsOf($user, 'loans_payable', $asOf);
            }

            $values[] = $total;
        }

        return ['labels' => $months, 'values' => $values];
    }

    private function systemAsOf(User $user, string $key, string $asOf): int
    {
        $ledger = $user->ledgerAccounts()->where('system_key', $key)->where('currency', 'EUR')->first();

        if (! $ledger) {
            return 0;
        }

        return $this->balances->ledgerMinor($ledger, $asOf);
    }

    /**
     * @return array<string, mixed>
     */
    public function bankFees(User $user): array
    {
        $from = now()->subYear()->addDay()->toDateString();
        $to = now()->toDateString();
        $trailing = (int) JournalEntry::query()
            ->where('user_id', $user->id)
            ->operational()
            ->where('type', JournalEntryType::Fee)
            ->where('currency', 'EUR')
            ->whereBetween('booked_on', [$from, $to])
            ->with('lines')
            ->get()
            ->sum(fn (JournalEntry $entry) => (int) ($entry->lines->first()->amount_minor ?? 0));

        $annualized = (int) RecurringRule::query()
            ->where('user_id', $user->id)
            ->where('purpose', RecurrencePurpose::AccountFee)
            ->where('status', RecurrenceStatus::Active)
            ->where('currency', 'EUR')
            ->get()
            ->sum(fn (RecurringRule $rule) => $rule->monthlyEquivalentMinor() * 12);

        return [
            'trailing_minor' => $trailing,
            'trailing' => Money::ofMinor($trailing, 'EUR')->format(),
            'annualized_minor' => $annualized,
            'annualized' => Money::ofMinor($annualized, 'EUR')->format(),
            'note' => $annualized === 0
                ? 'Non risultano canoni ricorrenti sui conti.'
                : 'Il costo annualizzato dei canoni attivi è '.$this->plain(Money::ofMinor($annualized, 'EUR')->format()).'.',
        ];
    }

    /**
     * @return list<string>
     */
    public function headlines(User $user): array
    {
        $messages = [];
        $budgets = \App\Models\Budget::query()->where('user_id', $user->id)->with('category')->get();

        if ($budgets->isNotEmpty() && ! $this->budgets->historyIsSufficient($user)) {
            $messages[] = 'Lo storico non basta per confrontare le abitudini: servono movimenti nei tre mesi precedenti.';
        }

        foreach (array_slice($this->budgets->comparisons($user, $budgets), 0, 3) as $message) {
            $messages[] = $message;
        }

        $until = now()->addDays(10)->toDateString();
        $projection = $this->forecast->project($user, $until);
        $outflow = 0;

        foreach ($projection['currencies'] as $currency) {
            if ($currency['currency'] !== 'EUR') {
                continue;
            }

            foreach ($currency['events'] as $event) {
                if ($event['delta'] < 0) {
                    $outflow += abs($event['delta']);
                }
            }
        }

        $messages[] = $outflow > 0
            ? 'Nei prossimi 10 giorni sono previsti '.$this->plain(Money::ofMinor($outflow, 'EUR')->format()).' di addebiti.'
            : 'Nei prossimi 10 giorni non risultano addebiti previsti.';

        $fees = $this->bankFees($user);
        $messages[] = $fees['note'];

        return $messages;
    }

    private function plain(string $formatted): string
    {
        return str_replace(' €', ' €', $formatted);
    }
}
