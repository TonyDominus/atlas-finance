<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\Actions\Books\ProvisionUserBooks;
use App\Domain\Money\Money;
use App\Enums\AccountStatus;
use App\Enums\LedgerAccountType;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;

final class PortfolioReader
{
    public function __construct(
        private BalanceReader $balances,
        private ProvisionUserBooks $books,
    ) {}

    /**
     * @return array{by_currency: list<array<string, mixed>>, accounts: list<array<string, mixed>>}
     */
    public function for(User $user): array
    {
        $accounts = Account::query()
            ->where('user_id', $user->id)
            ->with('institution')
            ->orderBy('name')
            ->get();

        $balanceMap = $this->balances->accountBalanceMap($user->id);
        $currencies = $accounts->pluck('currency')->unique()->values();

        if ($currencies->isEmpty()) {
            $currencies = collect(['EUR']);
        }

        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();
        $previousStart = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $previousEnd = now()->subMonthNoOverflow()->endOfMonth()->toDateString();
        $income = $this->balances->activityByCurrency($user->id, LedgerAccountType::Income, $start, $end);
        $expense = $this->balances->activityByCurrency($user->id, LedgerAccountType::Expense, $start, $end);
        $previousIncome = $this->balances->activityByCurrency($user->id, LedgerAccountType::Income, $previousStart, $previousEnd);
        $previousExpense = $this->balances->activityByCurrency($user->id, LedgerAccountType::Expense, $previousStart, $previousEnd);

        $byCurrency = [];

        foreach ($currencies as $currency) {
            $liquidity = 0;
            $includedAccounts = 0;

            foreach ($accounts as $account) {
                if ($account->currency !== $currency || $account->status === AccountStatus::Archived || ! $account->include_in_net_worth) {
                    continue;
                }

                $minor = $balanceMap[$account->id] ?? 0;
                $includedAccounts += $minor;

                if ($account->type->isLiquid()) {
                    $liquidity += $minor;
                }
            }

            $invested = $this->systemMinor($user, 'investments', $currency);
            $receivable = $this->systemMinor($user, 'loans_receivable', $currency);
            $payable = $this->systemMinor($user, 'loans_payable', $currency);
            $netWorth = $includedAccounts + $invested + $receivable - $payable;
            $monthIncome = $income[$currency] ?? 0;
            $monthExpense = $expense[$currency] ?? 0;
            $priorIncome = $previousIncome[$currency] ?? 0;
            $priorExpense = $previousExpense[$currency] ?? 0;
            $comparison = JournalEntry::query()
                ->where('user_id', $user->id)
                ->operational()
                ->where('currency', $currency)
                ->whereBetween('booked_on', [$previousStart, $previousEnd])
                ->exists();

            $byCurrency[] = [
                'currency' => $currency,
                'liquidity_minor' => $liquidity,
                'liquidity' => $this->format($liquidity, $currency),
                'invested_minor' => $invested,
                'invested' => $this->format($invested, $currency),
                'net_worth_minor' => $netWorth,
                'net_worth' => $this->format($netWorth, $currency),
                'income_month_minor' => $monthIncome,
                'income_month' => $this->format($monthIncome, $currency),
                'expense_month_minor' => $monthExpense,
                'expense_month' => $this->format($monthExpense, $currency),
                'savings_month_minor' => $monthIncome - $monthExpense,
                'savings_month' => $this->format($monthIncome - $monthExpense, $currency),
                'previous_income_minor' => $priorIncome,
                'previous_expense_minor' => $priorExpense,
                'income_change_percent' => $comparison ? $this->changePercent($monthIncome, $priorIncome) : null,
                'expense_change_percent' => $comparison ? $this->changePercent($monthExpense, $priorExpense) : null,
                'comparison_available' => $comparison,
            ];
        }

        return [
            'by_currency' => $byCurrency,
            'accounts' => $accounts->map(function (Account $account) use ($balanceMap) {
                $minor = $balanceMap[$account->id] ?? 0;

                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'institution' => $account->institution?->name,
                    'type' => $account->type->value,
                    'type_label' => $account->type->label(),
                    'currency' => $account->currency,
                    'color' => $account->color,
                    'icon' => $account->icon,
                    'status' => $account->status->value,
                    'status_label' => $account->status->label(),
                    'include_in_net_worth' => $account->include_in_net_worth,
                    'is_liquid' => $account->type->isLiquid(),
                    'balance_minor' => $minor,
                    'balance' => $this->format($minor, $account->currency),
                    'opening_balance' => $this->format($account->opening_balance_minor, $account->currency),
                    'opening_balance_on' => $account->opening_balance_on->toDateString(),
                    'notes' => $account->notes,
                ];
            })->all(),
        ];
    }

    private function systemMinor(User $user, string $key, string $currency): int
    {
        $ledger = $user->ledgerAccounts()
            ->where('system_key', $key)
            ->where('currency', $currency)
            ->first();

        if (! $ledger) {
            $this->books->ensureCurrency($user, $currency);
            $ledger = $this->books->system($user, $key, $currency);
        }

        return $this->balances->ledgerMinor($ledger);
    }

    private function format(int $minor, string $currency): string
    {
        return Money::ofMinor($minor, $currency)->format();
    }

    private function changePercent(int $current, int $previous): ?int
    {
        if ($previous === 0) {
            return null;
        }

        return (int) round((($current - $previous) * 100) / $previous);
    }
}
