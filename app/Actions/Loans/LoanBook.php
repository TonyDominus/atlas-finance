<?php

declare(strict_types=1);

namespace App\Actions\Loans;

use App\Actions\Books\ProvisionUserBooks;
use App\Domain\Money\Money;
use App\Enums\CounterpartyType;
use App\Enums\JournalEntryType;
use App\Enums\JournalOrigin;
use App\Enums\JournalSide;
use App\Enums\LoanDirection;
use App\Enums\LoanRepaymentKind;
use App\Models\Account;
use App\Models\Category;
use App\Models\Counterparty;
use App\Models\Loan;
use App\Models\LoanAllocation;
use App\Models\LoanDisbursement;
use App\Models\LoanRepayment;
use App\Models\User;
use App\Services\Ledger\JournalPoster;
use App\Services\Loans\LoanPosition;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class LoanBook
{
    public function __construct(
        private JournalPoster $poster,
        private ProvisionUserBooks $books,
        private LoanPosition $position,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function open(User $user, array $input): Loan
    {
        return DB::transaction(function () use ($user, $input) {
            $account = $this->account($user, (int) $input['account_id']);
            $amount = $this->money((string) $input['amount'], $account->currency, 'amount');
            $interest = $this->optionalMoney($input['agreed_interest'] ?? null, $account->currency);
            $counterparty = $this->counterparty($user, $input['counterparty_id'] ?? null, $input['counterparty_name'] ?? null);

            $loan = Loan::query()->create([
                'user_id' => $user->id,
                'counterparty_id' => $counterparty->id,
                'direction' => LoanDirection::from((string) $input['direction']),
                'currency' => $account->currency,
                'opened_on' => $input['opened_on'],
                'due_on' => ($input['due_on'] ?? null) ?: null,
                'description' => trim((string) $input['description']),
                'notes' => ($input['notes'] ?? null) ?: null,
                'agreed_interest_minor' => $interest?->amountMinor,
            ]);

            $this->disburse($user, $loan, $account, $amount, (string) $input['opened_on'], null);

            return $loan->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function addDisbursement(User $user, Loan $loan, array $input): LoanDisbursement
    {
        $this->owned($user, $loan);

        return DB::transaction(function () use ($user, $loan, $input) {
            $locked = Loan::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();
            $account = $this->account($user, (int) $input['account_id']);
            $this->assertCurrency($locked, $account);
            $amount = $this->money((string) $input['amount'], $account->currency, 'amount');

            return $this->disburse($user, $locked, $account, $amount, (string) $input['occurred_on'], $input['notes'] ?? null);
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function repay(User $user, array $input): LoanRepayment
    {
        return DB::transaction(function () use ($user, $input) {
            $kind = LoanRepaymentKind::from((string) $input['kind']);
            $surplusAsInterest = ($input['surplus_as'] ?? null) === 'interest';
            $rows = $this->normalizeAllocations($user, $input['allocations'] ?? [], $kind, $surplusAsInterest);

            if ($rows === []) {
                throw ValidationException::withMessages(['allocations' => 'Indica almeno un prestito da rimborsare.']);
            }

            $direction = $rows[0]['loan']->direction;
            $currency = $rows[0]['loan']->currency;
            $total = 0;

            foreach ($rows as $row) {
                if ($row['loan']->direction !== $direction || $row['loan']->currency !== $currency) {
                    throw ValidationException::withMessages([
                        'allocations' => 'Un rimborso può chiudere solo prestiti con la stessa direzione e valuta.',
                    ]);
                }

                $total += $row['principal'] + $row['interest'];
            }

            if ($total <= 0) {
                throw ValidationException::withMessages(['amount' => 'L\'importo del rimborso deve essere maggiore di zero.']);
            }

            $account = null;

            if ($kind === LoanRepaymentKind::Cash) {
                $account = $this->account($user, (int) ($input['account_id'] ?? 0));

                if ($account->currency !== $currency) {
                    throw ValidationException::withMessages(['account_id' => 'La valuta del conto non coincide con il prestito.']);
                }
            }

            $principal = array_sum(array_column($rows, 'principal'));
            $interest = array_sum(array_column($rows, 'interest'));
            $entry = $this->postRepayment($user, $direction, $kind, $account, $currency, $principal, $interest, $rows, (string) $input['occurred_on'], $input['notes'] ?? null);

            $repayment = LoanRepayment::query()->create([
                'user_id' => $user->id,
                'account_id' => $account?->id,
                'journal_entry_id' => $entry->id,
                'kind' => $kind,
                'amount_minor' => $total,
                'occurred_on' => $input['occurred_on'],
                'notes' => ($input['notes'] ?? null) ?: null,
            ]);

            foreach ($rows as $row) {
                LoanAllocation::query()->create([
                    'loan_repayment_id' => $repayment->id,
                    'loan_id' => $row['loan']->id,
                    'principal_minor' => $row['principal'],
                    'interest_minor' => $row['interest'],
                ]);
            }

            return $repayment->load('allocations');
        });
    }

    private function disburse(User $user, Loan $loan, Account $account, Money $amount, string $date, ?string $notes): LoanDisbursement
    {
        $loan->loadMissing('counterparty');
        $asset = $account->ledgerAccount()->firstOrFail();
        $key = $loan->direction === LoanDirection::Given ? 'loans_receivable' : 'loans_payable';
        $system = $this->books->system($user, $key, $account->currency);
        $label = $loan->direction === LoanDirection::Given ? 'Prestito concesso' : 'Prestito ricevuto';

        $lines = $loan->direction === LoanDirection::Given
            ? [
                $this->line($system->id, JournalSide::Debit, $amount),
                $this->line($asset->id, JournalSide::Credit, $amount),
            ]
            : [
                $this->line($asset->id, JournalSide::Debit, $amount),
                $this->line($system->id, JournalSide::Credit, $amount),
            ];

        $entry = $this->poster->post($user, [
            'type' => JournalEntryType::LoanDisbursement,
            'origin' => JournalOrigin::Manual,
            'occurred_on' => $date,
            'booked_on' => $date,
            'description' => $label.' a '.$loan->counterparty->name,
            'notes' => $notes,
            'currency' => $account->currency,
            'counterparty_id' => $loan->counterparty_id,
        ], $lines);

        return LoanDisbursement::query()->create([
            'loan_id' => $loan->id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'journal_entry_id' => $entry->id,
            'amount_minor' => $amount->amountMinor,
            'occurred_on' => $date,
            'notes' => $notes,
        ]);
    }

    /**
     * @param  list<array{loan: Loan, principal: int, interest: int}>  $rows
     */
    private function postRepayment(
        User $user,
        LoanDirection $direction,
        LoanRepaymentKind $kind,
        ?Account $account,
        string $currency,
        int $principal,
        int $interest,
        array $rows,
        string $date,
        ?string $notes,
    ): \App\Models\JournalEntry {
        $lines = [];
        $names = collect($rows)->map(fn (array $row) => $row['loan']->counterparty->name)->unique()->values();
        $description = ($kind === LoanRepaymentKind::Compensation ? 'Compensazione prestito ' : 'Rimborso prestito ').$names->join(', ');
        $categoryId = null;

        if ($kind === LoanRepaymentKind::Compensation) {
            $adjustment = $this->books->system($user, 'loan_adjustments', $currency);
            $systemKey = $direction === LoanDirection::Given ? 'loans_receivable' : 'loans_payable';
            $system = $this->books->system($user, $systemKey, $currency);

            if ($direction === LoanDirection::Given) {
                $lines[] = $this->line($adjustment->id, JournalSide::Debit, Money::ofMinor($principal, $currency));
                $lines[] = $this->line($system->id, JournalSide::Credit, Money::ofMinor($principal, $currency));
            } else {
                $lines[] = $this->line($system->id, JournalSide::Debit, Money::ofMinor($principal, $currency));
                $lines[] = $this->line($adjustment->id, JournalSide::Credit, Money::ofMinor($principal, $currency));
            }
        } else {
            $asset = $account->ledgerAccount()->firstOrFail();
            $cash = Money::ofMinor($principal + $interest, $currency);

            if ($direction === LoanDirection::Given) {
                $lines[] = $this->line($asset->id, JournalSide::Debit, $cash);

                if ($principal > 0) {
                    $lines[] = $this->line($this->books->system($user, 'loans_receivable', $currency)->id, JournalSide::Credit, Money::ofMinor($principal, $currency));
                }

                if ($interest > 0) {
                    $lines[] = $this->line($this->books->system($user, 'interest_income', $currency)->id, JournalSide::Credit, Money::ofMinor($interest, $currency));
                    $categoryId = Category::query()->where('user_id', $user->id)->where('slug', 'interessi')->value('id');
                }
            } else {
                if ($principal > 0) {
                    $lines[] = $this->line($this->books->system($user, 'loans_payable', $currency)->id, JournalSide::Debit, Money::ofMinor($principal, $currency));
                }

                if ($interest > 0) {
                    $lines[] = $this->line($this->books->system($user, 'expense', $currency)->id, JournalSide::Debit, Money::ofMinor($interest, $currency));
                }

                $lines[] = $this->line($asset->id, JournalSide::Credit, $cash);
            }
        }

        $counterpartyId = $names->count() === 1 ? $rows[0]['loan']->counterparty_id : null;

        return $this->poster->post($user, [
            'type' => JournalEntryType::LoanRepayment,
            'origin' => JournalOrigin::Manual,
            'occurred_on' => $date,
            'booked_on' => $date,
            'description' => $description,
            'notes' => $notes,
            'currency' => $currency,
            'category_id' => $categoryId,
            'counterparty_id' => $counterpartyId,
        ], $lines);
    }

    /**
     * @param  list<array<string, mixed>>  $allocations
     * @return list<array{loan: Loan, principal: int, interest: int}>
     */
    private function normalizeAllocations(User $user, array $allocations, LoanRepaymentKind $kind, bool $surplusAsInterest): array
    {
        $rows = [];

        foreach ($allocations as $index => $allocation) {
            $loan = Loan::query()->where('user_id', $user->id)->lockForUpdate()->find($allocation['loan_id'] ?? null);

            if (! $loan) {
                throw ValidationException::withMessages(["allocations.{$index}.loan_id" => 'Prestito non trovato.']);
            }

            $loan->load('counterparty');
            $principal = $this->money((string) ($allocation['principal'] ?? '0'), $loan->currency, "allocations.{$index}.principal")->amountMinor;
            $interest = $this->money((string) ($allocation['interest'] ?? '0'), $loan->currency, "allocations.{$index}.interest")->amountMinor;

            if ($principal < 0 || $interest < 0 || ($principal === 0 && $interest === 0)) {
                throw ValidationException::withMessages(["allocations.{$index}.principal" => 'Indica capitale o interessi maggiori di zero.']);
            }

            if ($kind === LoanRepaymentKind::Compensation && $interest > 0) {
                throw ValidationException::withMessages(["allocations.{$index}.interest" => 'Una compensazione non registra interessi. Usa un rimborso in denaro.']);
            }

            $residual = $this->position->residual($loan);

            if ($principal > $residual) {
                if (! $surplusAsInterest || $kind === LoanRepaymentKind::Compensation) {
                    $formatted = Money::ofMinor($residual, $loan->currency)->format();

                    throw ValidationException::withMessages([
                        "allocations.{$index}.principal" => "Il capitale supera il residuo di {$formatted}. Indica come trattare l'eccedenza, oppure riduci l'importo.",
                    ]);
                }

                $interest += $principal - $residual;
                $principal = $residual;
            }

            $rows[] = ['loan' => $loan, 'principal' => $principal, 'interest' => $interest];
        }

        return $rows;
    }

    private function account(User $user, int $accountId): Account
    {
        $account = Account::query()->where('user_id', $user->id)->lockForUpdate()->find($accountId);

        if (! $account) {
            throw ValidationException::withMessages(['account_id' => 'Conto non trovato.']);
        }

        if (! $account->isPostable()) {
            throw ValidationException::withMessages(['account_id' => 'Il conto non è attivo.']);
        }

        return $account;
    }

    private function assertCurrency(Loan $loan, Account $account): void
    {
        if ($loan->currency !== $account->currency) {
            throw ValidationException::withMessages(['account_id' => 'La valuta del conto non coincide con il prestito.']);
        }
    }

    private function owned(User $user, Loan $loan): void
    {
        if ($loan->user_id !== $user->id) {
            throw ValidationException::withMessages(['loan' => 'Prestito non disponibile.']);
        }
    }

    private function counterparty(User $user, mixed $id, mixed $name): Counterparty
    {
        if ($id) {
            $counterparty = Counterparty::query()->where('user_id', $user->id)->find($id);

            if (! $counterparty) {
                throw ValidationException::withMessages(['counterparty_id' => 'Controparte non trovata.']);
            }

            return $counterparty;
        }

        $name = trim((string) $name);

        if ($name === '') {
            throw ValidationException::withMessages(['counterparty_name' => 'Indica la persona.']);
        }

        $existing = Counterparty::query()
            ->where('user_id', $user->id)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->first();

        return $existing ?? Counterparty::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'type' => CounterpartyType::Person,
        ]);
    }

    private function money(string $input, string $currency, string $field): Money
    {
        try {
            return Money::parse($input === '' ? '0' : $input, $currency);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([$field => $exception->getMessage()]);
        }
    }

    private function optionalMoney(mixed $input, string $currency): ?Money
    {
        if ($input === null || trim((string) $input) === '') {
            return null;
        }

        $money = $this->money((string) $input, $currency, 'agreed_interest');

        return $money->isPositive() ? $money : null;
    }

    /**
     * @return array{ledger_account_id: int, side: JournalSide, amount_minor: int, currency: string}
     */
    private function line(int $ledgerId, JournalSide $side, Money $amount): array
    {
        return [
            'ledger_account_id' => $ledgerId,
            'side' => $side,
            'amount_minor' => $amount->amountMinor,
            'currency' => $amount->currency,
        ];
    }
}
