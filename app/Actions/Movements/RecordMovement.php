<?php

declare(strict_types=1);

namespace App\Actions\Movements;

use App\Actions\Books\ProvisionUserBooks;
use App\Actions\Categories\SuggestCategory;
use App\Domain\Ledger\InsufficientHoldingsException;
use App\Domain\Money\Money;
use App\Domain\Movements\MovementData;
use App\Enums\CategoryKind;
use App\Enums\JournalEntryType;
use App\Enums\JournalSide;
use App\Models\Account;
use App\Models\Category;
use App\Models\Counterparty;
use App\Models\JournalEntry;
use App\Models\Tag;
use App\Models\User;
use App\Services\Ledger\BalanceReader;
use App\Services\Ledger\JournalPoster;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordMovement
{
    public function __construct(
        private JournalPoster $poster,
        private ProvisionUserBooks $books,
        private SuggestCategory $suggest,
        private BalanceReader $balances,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function handle(User $user, MovementData $data, array $files = []): JournalEntry
    {
        if (! $data->type->isRecordable()) {
            throw ValidationException::withMessages([
                'type' => 'Questo tipo di movimento non si registra da qui.',
            ]);
        }

        if (! $data->amount->isPositive()) {
            throw ValidationException::withMessages([
                'amount' => 'L\'importo deve essere maggiore di zero.',
            ]);
        }

        return DB::transaction(function () use ($user, $data, $files) {
            $account = $this->account($user, $data->accountId, 'account_id');
            $this->assertCurrency($account, $data->amount, 'amount');

            $counterparty = $this->counterparty($user, $data);
            $category = $this->category($user, $data, $counterparty);

            $entry = match ($data->type) {
                JournalEntryType::Expense => $this->postCashExpense($user, $account, $data, $category, $counterparty, 'expense'),
                JournalEntryType::Fee => $this->postCashExpense($user, $account, $data, $category, $counterparty, 'bank_fees'),
                JournalEntryType::Refund => $this->postRefund($user, $account, $data, $category, $counterparty),
                JournalEntryType::Income => $this->postIncome($user, $account, $data, $category, $counterparty),
                JournalEntryType::Transfer => $this->postTransfer($user, $account, $data, $counterparty),
                JournalEntryType::InvestmentBuy => $this->postInvestment($user, $account, $data, $counterparty, true),
                JournalEntryType::InvestmentSell => $this->postInvestment($user, $account, $data, $counterparty, false),
                default => throw ValidationException::withMessages(['type' => 'Tipo non gestito.']),
            };

            $this->syncTags($user, $entry, $data->tags);
            $this->storeFiles($user, $entry, $files);

            return $entry->load(['lines.ledgerAccount', 'category', 'counterparty', 'tags', 'attachments', 'relatedEntries']);
        });
    }

    private function postCashExpense(User $user, Account $account, MovementData $data, ?Category $category, ?Counterparty $counterparty, string $systemKey): JournalEntry
    {
        $expense = $this->books->system($user, $systemKey, $account->currency);
        $asset = $account->ledgerAccount()->firstOrFail();

        return $this->poster->post($user, $this->attributes($data, $account->currency, $category, $counterparty), [
            $this->line($expense->id, JournalSide::Debit, $data->amount),
            $this->line($asset->id, JournalSide::Credit, $data->amount),
        ]);
    }

    private function postRefund(User $user, Account $account, MovementData $data, ?Category $category, ?Counterparty $counterparty): JournalEntry
    {
        $expense = $this->books->system($user, 'expense', $account->currency);
        $asset = $account->ledgerAccount()->firstOrFail();

        return $this->poster->post($user, $this->attributes($data, $account->currency, $category, $counterparty), [
            $this->line($asset->id, JournalSide::Debit, $data->amount),
            $this->line($expense->id, JournalSide::Credit, $data->amount),
        ]);
    }

    private function postIncome(User $user, Account $account, MovementData $data, ?Category $category, ?Counterparty $counterparty): JournalEntry
    {
        $key = $category?->slug === 'interessi' ? 'interest_income' : 'income';
        $income = $this->books->system($user, $key, $account->currency);
        $asset = $account->ledgerAccount()->firstOrFail();

        return $this->poster->post($user, $this->attributes($data, $account->currency, $category, $counterparty), [
            $this->line($asset->id, JournalSide::Debit, $data->amount),
            $this->line($income->id, JournalSide::Credit, $data->amount),
        ]);
    }

    private function postTransfer(User $user, Account $source, MovementData $data, ?Counterparty $counterparty): JournalEntry
    {
        if ($data->destinationAccountId === null) {
            throw ValidationException::withMessages(['destination_account_id' => 'Seleziona il conto di destinazione.']);
        }

        if ($data->destinationAccountId === $source->id) {
            throw ValidationException::withMessages(['destination_account_id' => 'I due conti devono essere diversi.']);
        }

        $destination = $this->account($user, $data->destinationAccountId, 'destination_account_id');

        if ($destination->currency !== $source->currency) {
            throw ValidationException::withMessages([
                'destination_account_id' => 'I trasferimenti tra valute diverse non sono disponibili: non applico cambi.',
            ]);
        }

        $entry = $this->poster->post($user, $this->attributes($data, $source->currency, null, $counterparty), [
            $this->line($destination->ledgerAccount()->firstOrFail()->id, JournalSide::Debit, $data->amount),
            $this->line($source->ledgerAccount()->firstOrFail()->id, JournalSide::Credit, $data->amount),
        ]);

        if ($data->fee instanceof Money && $data->fee->isPositive()) {
            if ($data->fee->currency !== $source->currency) {
                throw ValidationException::withMessages(['fee_amount' => 'La commissione deve essere nella valuta del conto.']);
            }

            $feeCategory = Category::query()
                ->where('user_id', $user->id)
                ->where('slug', 'commissioni-bancarie')
                ->first();

            $feeData = new MovementData(
                type: JournalEntryType::Fee,
                amount: $data->fee,
                occurredOn: $data->occurredOn,
                bookedOn: $data->bookedOn,
                description: 'Commissione su '.$data->description,
                accountId: $source->id,
                destinationAccountId: null,
                fee: null,
                categoryId: $feeCategory?->id,
                counterpartyId: $counterparty?->id,
                counterpartyName: null,
                notes: null,
                tags: [],
                externalId: $data->externalId ? $data->externalId.':fee' : null,
                origin: $data->origin,
            );

            $fee = $this->postCashExpense($user, $source, $feeData, $feeCategory, $counterparty, 'bank_fees');
            $fee->forceFill(['related_entry_id' => $entry->id])->save();
        }

        return $entry;
    }

    private function postInvestment(User $user, Account $account, MovementData $data, ?Counterparty $counterparty, bool $buy): JournalEntry
    {
        $holdings = $this->books->system($user, 'investments', $account->currency);
        $holdings = $holdings->newQuery()->whereKey($holdings->id)->lockForUpdate()->firstOrFail();
        $asset = $account->ledgerAccount()->firstOrFail();

        if (! $buy) {
            $available = $this->balances->ledgerMinor($holdings);

            if ($data->amount->amountMinor > $available) {
                throw new InsufficientHoldingsException('La vendita supera gli investimenti registrati. L\'eccedenza non viene trattata come ricavo.');
            }
        }

        $lines = $buy
            ? [
                $this->line($holdings->id, JournalSide::Debit, $data->amount),
                $this->line($asset->id, JournalSide::Credit, $data->amount),
            ]
            : [
                $this->line($asset->id, JournalSide::Debit, $data->amount),
                $this->line($holdings->id, JournalSide::Credit, $data->amount),
            ];

        return $this->poster->post($user, $this->attributes($data, $account->currency, null, $counterparty), $lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(MovementData $data, string $currency, ?Category $category, ?Counterparty $counterparty): array
    {
        return [
            'type' => $data->type,
            'origin' => $data->origin,
            'occurred_on' => $data->occurredOn,
            'booked_on' => $data->bookedOn,
            'description' => $data->description,
            'notes' => $data->notes,
            'currency' => $currency,
            'category_id' => $category?->id,
            'counterparty_id' => $counterparty?->id,
            'external_id' => $data->externalId,
            'status' => $data->status,
        ];
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

    private function account(User $user, int $accountId, string $field): Account
    {
        $account = Account::query()
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->find($accountId);

        if (! $account) {
            throw ValidationException::withMessages([$field => 'Conto non trovato.']);
        }

        if (! $account->isPostable()) {
            throw ValidationException::withMessages([$field => 'Il conto non è attivo.']);
        }

        return $account;
    }

    private function assertCurrency(Account $account, Money $money, string $field): void
    {
        if ($account->currency !== $money->currency) {
            throw ValidationException::withMessages([
                $field => 'La valuta non coincide con il conto.',
            ]);
        }
    }

    private function counterparty(User $user, MovementData $data): ?Counterparty
    {
        if ($data->counterpartyId) {
            $counterparty = Counterparty::query()
                ->where('user_id', $user->id)
                ->find($data->counterpartyId);

            if (! $counterparty) {
                throw ValidationException::withMessages(['counterparty_id' => 'Controparte non trovata.']);
            }

            return $counterparty;
        }

        $name = trim((string) $data->counterpartyName);

        if ($name === '') {
            return null;
        }

        $existing = Counterparty::query()
            ->where('user_id', $user->id)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->first();

        return $existing ?? Counterparty::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'type' => 'other',
        ]);
    }

    private function category(User $user, MovementData $data, ?Counterparty $counterparty): ?Category
    {
        $expected = match ($data->type) {
            JournalEntryType::Expense, JournalEntryType::Fee, JournalEntryType::Refund => CategoryKind::Expense,
            JournalEntryType::Income => CategoryKind::Income,
            default => null,
        };

        if ($expected === null) {
            return null;
        }

        if ($data->categoryId) {
            $category = Category::query()->where('user_id', $user->id)->find($data->categoryId);

            if (! $category || $category->kind !== $expected) {
                throw ValidationException::withMessages(['category_id' => 'Categoria non valida per questo movimento.']);
            }

            return $category;
        }

        $suggested = $this->suggest->match($user, $data->description, $counterparty?->name);

        if ($suggested && $suggested->kind === $expected) {
            return $suggested;
        }

        if ($data->type === JournalEntryType::Fee) {
            return Category::query()
                ->where('user_id', $user->id)
                ->where('slug', 'commissioni-bancarie')
                ->first();
        }

        return null;
    }

    /**
     * @param  list<string>  $tags
     */
    private function syncTags(User $user, JournalEntry $entry, array $tags): void
    {
        $ids = [];

        foreach ($tags as $name) {
            $name = trim($name);

            if ($name === '') {
                continue;
            }

            $ids[] = Tag::query()->firstOrCreate([
                'user_id' => $user->id,
                'name' => $name,
            ])->id;
        }

        $entry->tags()->sync($ids);
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    private function storeFiles(User $user, JournalEntry $entry, array $files): void
    {
        foreach ($files as $file) {
            $path = $file->store('attachments/'.$user->id, 'local');
            $original = basename(str_replace('\\', '/', $file->getClientOriginalName()));

            $entry->attachments()->create([
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => $original !== '' ? $original : 'allegato',
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize() ?: 0,
            ]);
        }
    }
}
