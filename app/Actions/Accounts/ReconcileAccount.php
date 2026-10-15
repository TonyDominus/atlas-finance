<?php

declare(strict_types=1);

namespace App\Actions\Accounts;

use App\Actions\Books\ProvisionUserBooks;
use App\Domain\Money\Money;
use App\Enums\AccountStatus;
use App\Enums\JournalEntryType;
use App\Enums\JournalOrigin;
use App\Enums\JournalSide;
use App\Models\Account;
use App\Models\ReconciliationRecord;
use App\Models\User;
use App\Services\Ledger\BalanceReader;
use App\Services\Ledger\JournalPoster;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class ReconcileAccount
{
    public function __construct(
        private BalanceReader $balances,
        private JournalPoster $poster,
        private ProvisionUserBooks $books,
    ) {}

    public function handle(
        User $user,
        Account $account,
        string $statementBalance,
        string $reconciledOn,
        bool $postAdjustment,
        ?string $notes,
    ): ReconciliationRecord {
        if ($account->user_id !== $user->id) {
            throw ValidationException::withMessages(['statement_balance' => 'Conto non disponibile.']);
        }

        if ($account->status === AccountStatus::Archived) {
            throw ValidationException::withMessages(['statement_balance' => 'Un conto archiviato non si riconcilia.']);
        }

        try {
            $statement = Money::parse($statementBalance, $account->currency);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['statement_balance' => $exception->getMessage()]);
        }

        return DB::transaction(function () use ($user, $account, $statement, $reconciledOn, $postAdjustment, $notes) {
            $computed = $this->balances->accountMinor($account, $reconciledOn);
            $difference = $statement->amountMinor - $computed;
            $entryId = null;

            if ($difference !== 0 && $postAdjustment) {
                $asset = $account->ledgerAccount()->firstOrFail();
                $equity = $this->books->system($user, 'reconciliation', $account->currency);
                $amount = abs($difference);
                $lines = $difference > 0
                    ? [
                        ['ledger_account_id' => $asset->id, 'side' => JournalSide::Debit, 'amount_minor' => $amount, 'currency' => $account->currency],
                        ['ledger_account_id' => $equity->id, 'side' => JournalSide::Credit, 'amount_minor' => $amount, 'currency' => $account->currency],
                    ]
                    : [
                        ['ledger_account_id' => $equity->id, 'side' => JournalSide::Debit, 'amount_minor' => $amount, 'currency' => $account->currency],
                        ['ledger_account_id' => $asset->id, 'side' => JournalSide::Credit, 'amount_minor' => $amount, 'currency' => $account->currency],
                    ];

                $entry = $this->poster->post($user, [
                    'type' => JournalEntryType::Reconciliation,
                    'origin' => JournalOrigin::Manual,
                    'occurred_on' => $reconciledOn,
                    'booked_on' => $reconciledOn,
                    'description' => 'Rettifica di riconciliazione '.$account->name,
                    'notes' => $notes,
                    'currency' => $account->currency,
                ], $lines);

                $entryId = $entry->id;
            }

            return ReconciliationRecord::query()->create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'reconciled_on' => $reconciledOn,
                'statement_balance_minor' => $statement->amountMinor,
                'computed_balance_minor' => $computed,
                'difference_minor' => $difference,
                'journal_entry_id' => $entryId,
                'notes' => $notes,
            ]);
        });
    }
}
