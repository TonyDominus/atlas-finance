<?php

declare(strict_types=1);

namespace App\Actions\Accounts;

use App\Actions\Books\ProvisionUserBooks;
use App\Domain\Money\Money;
use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Enums\JournalEntryType;
use App\Enums\JournalOrigin;
use App\Enums\JournalSide;
use App\Enums\LedgerAccountType;
use App\Models\Account;
use App\Models\Institution;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Ledger\JournalPoster;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class CreateAccount
{
    public function __construct(
        private ProvisionUserBooks $books,
        private JournalPoster $poster,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     institution?: string|null,
     *     type: string,
     *     currency: string,
     *     opening_balance: string,
     *     opening_balance_on: string,
     *     color: string,
     *     icon: string,
     *     include_in_net_worth: bool,
     *     notes?: string|null
     * }  $data
     */
    public function handle(User $user, array $data): Account
    {
        $currency = strtoupper($data['currency']);

        try {
            $opening = Money::parse($data['opening_balance'], $currency);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'opening_balance' => $exception->getMessage(),
            ]);
        }

        return DB::transaction(function () use ($user, $data, $currency, $opening) {
            $this->books->ensureCurrency($user, $currency);

            $institutionId = null;
            $institutionName = trim((string) ($data['institution'] ?? ''));

            if ($institutionName !== '') {
                $institutionId = Institution::query()->firstOrCreate(
                    ['user_id' => $user->id, 'name' => $institutionName],
                )->id;
            }

            $account = Account::query()->create([
                'user_id' => $user->id,
                'institution_id' => $institutionId,
                'name' => trim($data['name']),
                'type' => AccountType::from($data['type']),
                'currency' => $currency,
                'color' => $data['color'],
                'icon' => $data['icon'],
                'status' => AccountStatus::Active,
                'include_in_net_worth' => $data['include_in_net_worth'],
                'opening_balance_minor' => $opening->amountMinor,
                'opening_balance_on' => $data['opening_balance_on'],
                'notes' => $data['notes'] ?? null,
            ]);

            $ledger = LedgerAccount::query()->create([
                'user_id' => $user->id,
                'financial_account_id' => $account->id,
                'code' => 'asset.account.'.$account->id,
                'name' => $account->name,
                'type' => LedgerAccountType::Asset,
                'system_key' => null,
                'currency' => $currency,
                'is_system' => false,
            ]);

            if (! $opening->isZero()) {
                $equity = $this->books->system($user, 'opening_equity', $currency);
                $amount = abs($opening->amountMinor);
                $lines = $opening->isPositive()
                    ? [
                        $this->line($ledger->id, JournalSide::Debit, $amount, $currency),
                        $this->line($equity->id, JournalSide::Credit, $amount, $currency),
                    ]
                    : [
                        $this->line($equity->id, JournalSide::Debit, $amount, $currency),
                        $this->line($ledger->id, JournalSide::Credit, $amount, $currency),
                    ];

                $this->poster->post($user, [
                    'type' => JournalEntryType::OpeningBalance,
                    'origin' => JournalOrigin::Manual,
                    'occurred_on' => $data['opening_balance_on'],
                    'booked_on' => $data['opening_balance_on'],
                    'description' => 'Saldo iniziale '.$account->name,
                    'currency' => $currency,
                ], $lines);
            }

            return $account->load('institution', 'ledgerAccount');
        });
    }

    /**
     * @return array{ledger_account_id: int, side: JournalSide, amount_minor: int, currency: string}
     */
    private function line(int $ledgerId, JournalSide $side, int $amount, string $currency): array
    {
        return [
            'ledger_account_id' => $ledgerId,
            'side' => $side,
            'amount_minor' => $amount,
            'currency' => $currency,
        ];
    }
}
