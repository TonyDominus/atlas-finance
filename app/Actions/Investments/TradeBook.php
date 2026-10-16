<?php

declare(strict_types=1);

namespace App\Actions\Investments;

use App\Actions\Books\ProvisionUserBooks;
use App\Actions\Movements\VoidMovement;
use App\Domain\Investments\Decimal;
use App\Domain\Money\Money;
use App\Enums\JournalEntryType;
use App\Enums\JournalOrigin;
use App\Enums\JournalSide;
use App\Enums\TradeSide;
use App\Models\Account;
use App\Models\Category;
use App\Models\Instrument;
use App\Models\JournalEntry;
use App\Models\SecurityTrade;
use App\Models\User;
use App\Services\Investments\PositionReader;
use App\Services\Ledger\JournalPoster;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class TradeBook
{
    public function __construct(
        private JournalPoster $poster,
        private ProvisionUserBooks $books,
        private PositionReader $positions,
        private VoidMovement $voids,
    ) {}

    /**
     * Buys debit the investments ledger at the price paid. The fee is a separate bank fee entry.
     *
     * @param  array<string, mixed>  $input
     */
    public function record(User $user, array $input): SecurityTrade
    {
        $side = TradeSide::from((string) $input['side']);

        return DB::transaction(function () use ($user, $input, $side) {
            $instrument = $this->instrument($user, (int) $input['instrument_id']);
            $account = $this->account($user, (int) $input['account_id'], $instrument);
            $tradedOn = (string) $input['traded_on'];
            $quantity = $this->quantity((string) $input['quantity']);
            $gross = $this->money((string) $input['amount'], $account->currency, 'amount');
            $fee = $this->optionalMoney($input['fee'] ?? null, $account->currency);

            $this->lockPosition($user, $instrument, $account);
            $position = $this->positions->position($user, $instrument->id, $account->id);

            if ($position['last_traded_on'] !== null && $tradedOn < $position['last_traded_on']) {
                throw ValidationException::withMessages([
                    'traded_on' => 'Esiste già un\'operazione successiva su questo titolo e conto. Registra le operazioni in ordine di data.',
                ]);
            }

            $investments = $this->books->system($user, 'investments', $account->currency);
            $asset = $account->ledgerAccount()->firstOrFail();
            $label = Decimal::format($quantity).' '.$instrument->symbol;

            if ($side === TradeSide::Buy) {
                $cost = $gross->amountMinor;
                $entry = $this->poster->post($user, $this->attributes(JournalEntryType::InvestmentBuy, $tradedOn, 'Acquisto '.$label, $account->currency, $input), [
                    $this->line($investments->id, JournalSide::Debit, $gross->amountMinor, $account->currency),
                    $this->line($asset->id, JournalSide::Credit, $gross->amountMinor, $account->currency),
                ]);
            } else {
                if ($quantity > $position['quantity_units']) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Quantità superiore a quella in portafoglio su questo conto ('.Decimal::format($position['quantity_units']).').',
                    ]);
                }

                $cost = $quantity === $position['quantity_units']
                    ? $position['cost_minor']
                    : Decimal::proportion($position['cost_minor'], $quantity, $position['quantity_units']);
                $result = $gross->amountMinor - $cost;
                $lines = [
                    $this->line($asset->id, JournalSide::Debit, $gross->amountMinor, $account->currency),
                ];

                if ($cost > 0) {
                    $lines[] = $this->line($investments->id, JournalSide::Credit, $cost, $account->currency);
                }

                if ($result !== 0) {
                    $results = $this->books->system($user, 'investment_results', $account->currency);
                    $lines[] = $this->line($results->id, $result > 0 ? JournalSide::Credit : JournalSide::Debit, abs($result), $account->currency);
                }

                $entry = $this->poster->post($user, $this->attributes(JournalEntryType::InvestmentSell, $tradedOn, 'Vendita '.$label, $account->currency, $input), $lines);
            }

            if ($fee instanceof Money && $fee->isPositive()) {
                $this->postFee($user, $account, $entry, $fee, $tradedOn, $label);
            }

            return SecurityTrade::query()->create([
                'user_id' => $user->id,
                'instrument_id' => $instrument->id,
                'account_id' => $account->id,
                'journal_entry_id' => $entry->id,
                'side' => $side,
                'quantity_units' => $quantity,
                'gross_minor' => $gross->amountMinor,
                'fee_minor' => $fee?->amountMinor ?? 0,
                'cost_minor' => $cost,
                'currency' => $account->currency,
                'traded_on' => $tradedOn,
            ]);
        });
    }

    /**
     * Only the latest trade of a position can be voided, so later average costs never go stale.
     */
    public function void(User $user, SecurityTrade $trade, string $reason): void
    {
        if ($trade->user_id !== $user->id) {
            throw ValidationException::withMessages(['trade' => 'Operazione non disponibile.']);
        }

        DB::transaction(function () use ($user, $trade, $reason) {
            $instrument = $this->instrument($user, $trade->instrument_id);
            $account = Account::query()->where('user_id', $user->id)->findOrFail($trade->account_id);
            $this->lockPosition($user, $instrument, $account);

            $latest = SecurityTrade::query()
                ->where('user_id', $user->id)
                ->where('instrument_id', $trade->instrument_id)
                ->where('account_id', $trade->account_id)
                ->active()
                ->orderByDesc('traded_on')
                ->orderByDesc('id')
                ->first();

            if (! $latest || $latest->id !== $trade->id) {
                throw ValidationException::withMessages([
                    'trade' => 'Si annulla solo l\'ultima operazione del titolo su questo conto. Annulla prima quelle successive.',
                ]);
            }

            $this->voids->handle($user, JournalEntry::query()->findOrFail($trade->journal_entry_id), $reason, fromTrade: true);
        });
    }

    private function postFee(User $user, Account $account, JournalEntry $entry, Money $fee, string $tradedOn, string $label): void
    {
        $category = Category::query()
            ->where('user_id', $user->id)
            ->where('slug', 'commissioni-bancarie')
            ->first();

        $feeEntry = $this->poster->post($user, [
            'type' => JournalEntryType::Fee,
            'origin' => JournalOrigin::Manual,
            'occurred_on' => $tradedOn,
            'booked_on' => $tradedOn,
            'description' => 'Commissione su '.$label,
            'currency' => $account->currency,
            'category_id' => $category?->id,
        ], [
            $this->line($this->books->system($user, 'bank_fees', $account->currency)->id, JournalSide::Debit, $fee->amountMinor, $account->currency),
            $this->line($account->ledgerAccount()->firstOrFail()->id, JournalSide::Credit, $fee->amountMinor, $account->currency),
        ]);

        $feeEntry->forceFill(['related_entry_id' => $entry->id])->save();
    }

    private function lockPosition(User $user, Instrument $instrument, Account $account): void
    {
        Instrument::query()->whereKey($instrument->id)->lockForUpdate()->first();
        Account::query()->where('user_id', $user->id)->whereKey($account->id)->lockForUpdate()->first();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function attributes(JournalEntryType $type, string $tradedOn, string $description, string $currency, array $input): array
    {
        return [
            'type' => $type,
            'origin' => JournalOrigin::Manual,
            'occurred_on' => $tradedOn,
            'booked_on' => $tradedOn,
            'description' => $description,
            'notes' => ($input['notes'] ?? null) ?: null,
            'currency' => $currency,
        ];
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

    private function instrument(User $user, int $id): Instrument
    {
        $instrument = Instrument::query()->where('user_id', $user->id)->find($id);

        if (! $instrument) {
            throw ValidationException::withMessages(['instrument_id' => 'Titolo non trovato.']);
        }

        return $instrument;
    }

    private function account(User $user, int $id, Instrument $instrument): Account
    {
        $account = Account::query()->where('user_id', $user->id)->find($id);

        if (! $account) {
            throw ValidationException::withMessages(['account_id' => 'Conto non trovato.']);
        }

        if (! $account->isPostable()) {
            throw ValidationException::withMessages(['account_id' => 'Il conto non è attivo.']);
        }

        if ($account->currency !== $instrument->currency) {
            throw ValidationException::withMessages([
                'account_id' => "Il titolo è in {$instrument->currency}, il conto in {$account->currency}. Non applico cambi: usa un conto nella stessa valuta.",
            ]);
        }

        return $account;
    }

    private function quantity(string $input): int
    {
        try {
            $units = Decimal::parse($input, 'Quantità');
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['quantity' => $exception->getMessage()]);
        }

        if ($units <= 0) {
            throw ValidationException::withMessages(['quantity' => 'La quantità deve essere maggiore di zero.']);
        }

        return $units;
    }

    private function money(string $input, string $currency, string $field): Money
    {
        try {
            $money = Money::parse($input, $currency);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([$field => $exception->getMessage()]);
        }

        if (! $money->isPositive()) {
            throw ValidationException::withMessages([$field => 'L\'importo deve essere maggiore di zero.']);
        }

        return $money;
    }

    private function optionalMoney(mixed $input, string $currency): ?Money
    {
        if ($input === null || trim((string) $input) === '') {
            return null;
        }

        try {
            $money = Money::parse((string) $input, $currency);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['fee' => $exception->getMessage()]);
        }

        if ($money->isNegative()) {
            throw ValidationException::withMessages(['fee' => 'La commissione non può essere negativa.']);
        }

        return $money->isZero() ? null : $money;
    }
}
