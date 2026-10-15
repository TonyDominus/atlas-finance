<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Actions\Movements\RecordMovement;
use App\Domain\Assistant\AmbiguousTargetException;
use App\Domain\Assistant\UnknownAccountException;
use App\Domain\Money\Money;
use App\Domain\Movements\MovementData;
use App\Enums\AccountStatus;
use App\Enums\JournalEntryType;
use App\Enums\JournalOrigin;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Ledger\PortfolioReader;
use Illuminate\Database\Eloquent\Collection;

/**
 * Application service for a future Telegram bot.
 * Natural-language parsing stays outside this class: ambiguous account names are rejected.
 */
final class FinanceAssistant
{
    public function __construct(
        private PortfolioReader $portfolio,
        private RecordMovement $movements,
    ) {}

    /**
     * @return array{by_currency: list<array<string, mixed>>, accounts: list<array<string, mixed>>}
     */
    public function balance(User $user): array
    {
        return $this->portfolio->for($user);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function accounts(User $user): array
    {
        return $this->portfolio->for($user)['accounts'];
    }

    public function spend(User $user, string $amount, string $description, string $accountName, ?string $occurredOn = null): JournalEntry
    {
        return $this->record($user, JournalEntryType::Expense, $amount, $description, $accountName, null, $occurredOn);
    }

    public function income(User $user, string $amount, string $description, string $accountName, ?string $occurredOn = null): JournalEntry
    {
        return $this->record($user, JournalEntryType::Income, $amount, $description, $accountName, null, $occurredOn);
    }

    public function transfer(User $user, string $amount, string $description, string $fromAccount, string $toAccount, ?string $occurredOn = null): JournalEntry
    {
        $source = $this->resolveAccount($user, $fromAccount);
        $destination = $this->resolveAccount($user, $toAccount);

        return $this->record(
            $user,
            JournalEntryType::Transfer,
            $amount,
            $description,
            $source->name,
            $destination->id,
            $occurredOn,
            $source,
        );
    }

    /**
     * @return array{available: bool, message: string}
     */
    public function loans(): array
    {
        return [
            'available' => false,
            'message' => 'I prestiti non sono ancora disponibili in questa versione.',
        ];
    }

    /**
     * @return array{available: bool, message: string}
     */
    public function upcoming(): array
    {
        return [
            'available' => false,
            'message' => 'Le scadenze previste arriveranno con il motore delle ricorrenze.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function report(User $user): array
    {
        $snapshot = $this->portfolio->for($user);

        return [
            'period' => now()->translatedFormat('F Y'),
            'by_currency' => $snapshot['by_currency'],
        ];
    }

    private function record(
        User $user,
        JournalEntryType $type,
        string $amount,
        string $description,
        string $accountName,
        ?int $destinationId,
        ?string $occurredOn,
        ?Account $resolved = null,
    ): JournalEntry {
        $account = $resolved ?? $this->resolveAccount($user, $accountName);
        $date = $occurredOn ?? now()->toDateString();

        return $this->movements->handle($user, new MovementData(
            type: $type,
            amount: Money::parse($amount, $account->currency),
            occurredOn: $date,
            bookedOn: $date,
            description: $description,
            accountId: $account->id,
            destinationAccountId: $destinationId,
            fee: null,
            categoryId: null,
            counterpartyId: null,
            counterpartyName: null,
            notes: null,
            tags: [],
            externalId: null,
            origin: JournalOrigin::Telegram,
        ));
    }

    private function resolveAccount(User $user, string $name): Account
    {
        $name = trim($name);

        if ($name === '') {
            throw new UnknownAccountException($name);
        }

        $active = Account::query()
            ->where('user_id', $user->id)
            ->where('status', AccountStatus::Active);

        /** @var Collection<int, Account> $exact */
        $exact = (clone $active)->whereRaw('lower(name) = ?', [mb_strtolower($name)])->get();

        if ($exact->count() === 1) {
            return $exact->first();
        }

        if ($exact->count() > 1) {
            throw new AmbiguousTargetException($exact->pluck('name')->all());
        }

        $needle = '%'.addcslashes($name, '%_\\').'%';

        /** @var Collection<int, Account> $partial */
        $partial = (clone $active)->where('name', 'like', $needle)->orderBy('name')->get();

        if ($partial->count() === 1) {
            return $partial->first();
        }

        if ($partial->count() > 1) {
            throw new AmbiguousTargetException($partial->pluck('name')->all());
        }

        throw new UnknownAccountException($name);
    }
}
