<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\Enums\JournalEntryStatus;
use App\Enums\JournalSide;
use App\Enums\LedgerAccountType;
use App\Models\Account;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use Illuminate\Support\Facades\DB;

final class BalanceReader
{
    public function accountMinor(Account $account, ?string $asOf = null): int
    {
        $ledgerId = $account->ledgerAccount?->id;

        if ($ledgerId === null) {
            return 0;
        }

        return $this->netDebit($ledgerId, $asOf);
    }

    public function ledgerMinor(LedgerAccount $ledger, ?string $asOf = null): int
    {
        $netDebit = $this->netDebit($ledger->id, $asOf);

        return match ($ledger->type) {
            LedgerAccountType::Asset, LedgerAccountType::Expense => $netDebit,
            LedgerAccountType::Liability, LedgerAccountType::Equity, LedgerAccountType::Income => -$netDebit,
        };
    }

    /**
     * @return array<int, int>
     */
    public function accountBalanceMap(int $userId): array
    {
        $rows = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->join('ledger_accounts as la', 'la.id', '=', 'jl.ledger_account_id')
            ->where('je.user_id', $userId)
            ->where('je.status', JournalEntryStatus::Posted->value)
            ->whereNotNull('la.financial_account_id')
            ->groupBy('la.financial_account_id')
            ->select('la.financial_account_id as account_id')
            ->selectRaw("SUM(CASE WHEN jl.side = ? THEN jl.amount_minor ELSE -jl.amount_minor END) as balance_minor", [JournalSide::Debit->value])
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row->account_id] = (int) $row->balance_minor;
        }

        return $map;
    }

    /**
     * Net amount by currency for income or expense ledgers, excluding voided events and reversals.
     *
     * @return array<string, int>
     */
    public function activityByCurrency(int $userId, LedgerAccountType $type, string $from, string $to): array
    {
        $rows = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->join('ledger_accounts as la', 'la.id', '=', 'jl.ledger_account_id')
            ->where('je.user_id', $userId)
            ->where('je.status', JournalEntryStatus::Posted->value)
            ->whereNull('je.voided_at')
            ->where('je.type', '!=', 'reversal')
            ->where('la.type', $type->value)
            ->whereBetween('je.booked_on', [$from, $to])
            ->groupBy('la.currency')
            ->select('la.currency as currency')
            ->selectRaw('SUM(CASE WHEN jl.side = ? THEN jl.amount_minor ELSE 0 END) as debits', [JournalSide::Debit->value])
            ->selectRaw('SUM(CASE WHEN jl.side = ? THEN jl.amount_minor ELSE 0 END) as credits', [JournalSide::Credit->value])
            ->get();

        $normalDebit = in_array($type, [LedgerAccountType::Asset, LedgerAccountType::Expense], true);
        $totals = [];

        foreach ($rows as $row) {
            $debits = (int) $row->debits;
            $credits = (int) $row->credits;
            $totals[(string) $row->currency] = $normalDebit ? $debits - $credits : $credits - $debits;
        }

        return $totals;
    }

    private function netDebit(int $ledgerAccountId, ?string $asOf): int
    {
        $query = JournalLine::query()
            ->where('ledger_account_id', $ledgerAccountId)
            ->whereHas('entry', function ($builder) use ($asOf) {
                $builder->where('status', JournalEntryStatus::Posted->value);

                if ($asOf !== null) {
                    $builder->whereDate('booked_on', '<=', $asOf);
                }
            });

        $debits = (int) (clone $query)->where('side', JournalSide::Debit->value)->sum('amount_minor');
        $credits = (int) (clone $query)->where('side', JournalSide::Credit->value)->sum('amount_minor');

        return $debits - $credits;
    }
}
