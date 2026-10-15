<?php

use App\Actions\Accounts\CreateAccount;
use App\Actions\Accounts\ReconcileAccount;
use App\Actions\Movements\RecordMovement;
use App\Actions\Movements\VoidMovement;
use App\Domain\Assistant\AmbiguousTargetException;
use App\Domain\Ledger\DuplicateMovementException;
use App\Domain\Ledger\InsufficientHoldingsException;
use App\Domain\Ledger\UnbalancedJournalException;
use App\Domain\Money\Money;
use App\Domain\Movements\MovementData;
use App\Enums\JournalEntryType;
use App\Enums\JournalOrigin;
use App\Enums\JournalSide;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Assistant\FinanceAssistant;
use App\Services\Ledger\BalanceReader;
use App\Services\Ledger\JournalPoster;
use App\Services\Ledger\PortfolioReader;

function atlasAccount(User $user, string $name, string $opening = '0,00', string $type = 'checking'): Account
{
    return app(CreateAccount::class)->handle($user, [
        'name' => $name,
        'institution' => $name,
        'type' => $type,
        'currency' => 'EUR',
        'opening_balance' => $opening,
        'opening_balance_on' => now()->toDateString(),
        'color' => '#115e59',
        'icon' => 'landmark',
        'include_in_net_worth' => true,
        'notes' => null,
    ]);
}

function atlasMove(
    User $user,
    Account $account,
    JournalEntryType $type,
    string $amount,
    string $description,
    ?int $destinationId = null,
    ?Money $fee = null,
    ?string $externalId = null,
): JournalEntry {
    return app(RecordMovement::class)->handle($user, new MovementData(
        type: $type,
        amount: Money::parse($amount, 'EUR'),
        occurredOn: now()->toDateString(),
        bookedOn: now()->toDateString(),
        description: $description,
        accountId: $account->id,
        destinationAccountId: $destinationId,
        fee: $fee,
        categoryId: null,
        counterpartyId: null,
        counterpartyName: null,
        notes: null,
        tags: [],
        externalId: $externalId,
        origin: JournalOrigin::Manual,
    ));
}

test('registration provisions categories and ledger accounts', function () {
    $this->post('/register', [
        'name' => 'Tony',
        'email' => 'tony@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::query()->where('email', 'tony@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->categories()->where('slug', 'alimentari')->exists())->toBeTrue()
        ->and($user->categories()->where('slug', 'stipendio')->exists())->toBeTrue()
        ->and($user->ledgerAccounts()->where('system_key', 'expense')->where('currency', 'EUR')->exists())->toBeTrue();
});

test('opening balance is the account balance', function () {
    $user = User::factory()->create();
    $account = atlasAccount($user, 'Intesa Sanpaolo', '1.000,00');

    expect(app(BalanceReader::class)->accountMinor($account))->toBe(100000);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('snapshot.by_currency.0.net_worth_minor', 100000)
            ->where('snapshot.by_currency.0.liquidity_minor', 100000));
});

test('expense reduces cash and increases expenses without touching income', function () {
    $user = User::factory()->create();
    $account = atlasAccount($user, 'HYPE', '100,00', 'card');

    atlasMove($user, $account, JournalEntryType::Expense, '12,50', 'Caffè');

    $snapshot = app(PortfolioReader::class)->for($user)['by_currency'][0];

    expect(app(BalanceReader::class)->accountMinor($account->fresh()))->toBe(8750)
        ->and($snapshot['expense_month_minor'])->toBe(1250)
        ->and($snapshot['income_month_minor'])->toBe(0)
        ->and($snapshot['net_worth_minor'])->toBe(8750);
});

test('income increases cash and income', function () {
    $user = User::factory()->create();
    $account = atlasAccount($user, 'Intesa', '0,00');

    atlasMove($user, $account, JournalEntryType::Income, '1.500,00', 'Stipendio');

    $snapshot = app(PortfolioReader::class)->for($user)['by_currency'][0];

    expect(app(BalanceReader::class)->accountMinor($account->fresh()))->toBe(150000)
        ->and($snapshot['income_month_minor'])->toBe(150000)
        ->and($snapshot['expense_month_minor'])->toBe(0);
});

test('transfer does not change net worth and a separate fee does', function () {
    $user = User::factory()->create();
    $intesa = atlasAccount($user, 'Intesa Sanpaolo', '1.000,00');
    $hype = atlasAccount($user, 'HYPE', '50,00', 'card');

    atlasMove(
        $user,
        $intesa,
        JournalEntryType::Transfer,
        '300,00',
        'Ricarica HYPE',
        $hype->id,
        Money::parse('1,50'),
    );

    $reader = app(BalanceReader::class);
    $snapshot = app(PortfolioReader::class)->for($user)['by_currency'][0];

    expect($reader->accountMinor($intesa->fresh()))->toBe(69850)
        ->and($reader->accountMinor($hype->fresh()))->toBe(35000)
        ->and($snapshot['net_worth_minor'])->toBe(104850)
        ->and($snapshot['expense_month_minor'])->toBe(150)
        ->and($snapshot['income_month_minor'])->toBe(0)
        ->and($snapshot['liquidity_minor'])->toBe(104850);
});

test('netflix is classified as a subscription and can be corrected by category ownership rules', function () {
    $user = User::factory()->create();
    $account = atlasAccount($user, 'HYPE', '100,00', 'card');

    $entry = atlasMove($user, $account, JournalEntryType::Expense, '15,99', 'Canone Netflix');

    expect($entry->category?->slug)->toBe('abbonamenti')
        ->and($entry->lines->contains(fn ($line) => $line->ledgerAccount->system_key === 'expense' && $line->side === JournalSide::Debit))->toBeTrue();
});

test('duplicate external ids are rejected', function () {
    $user = User::factory()->create();
    $account = atlasAccount($user, 'PayPal', '40,00', 'wallet');

    atlasMove($user, $account, JournalEntryType::Expense, '5,00', 'Import', null, null, 'csv-1');

    expect(fn () => atlasMove($user, $account, JournalEntryType::Expense, '5,00', 'Import di nuovo', null, null, 'csv-1'))
        ->toThrow(DuplicateMovementException::class);

    expect(JournalEntry::query()->where('external_id', 'csv-1')->count())->toBe(1);
});

test('a refund reduces expenses and is not income', function () {
    $user = User::factory()->create();
    $account = atlasAccount($user, 'Contanti', '50,00', 'cash');

    atlasMove($user, $account, JournalEntryType::Expense, '20,00', 'Maglietta');
    atlasMove($user, $account, JournalEntryType::Refund, '20,00', 'Reso maglietta');

    $snapshot = app(PortfolioReader::class)->for($user)['by_currency'][0];

    expect(app(BalanceReader::class)->accountMinor($account->fresh()))->toBe(5000)
        ->and($snapshot['expense_month_minor'])->toBe(0)
        ->and($snapshot['income_month_minor'])->toBe(0);
});

test('voiding an expense restores the balance and keeps history', function () {
    $user = User::factory()->create();
    $account = atlasAccount($user, 'Intesa', '80,00');
    $entry = atlasMove($user, $account, JournalEntryType::Expense, '10,00', 'Pranzo');

    app(VoidMovement::class)->handle($user, $entry, 'Registrato due volte');

    expect(app(BalanceReader::class)->accountMinor($account->fresh()))->toBe(8000)
        ->and($entry->fresh()->voided_at)->not->toBeNull()
        ->and(JournalEntry::query()->where('reverses_entry_id', $entry->id)->exists())->toBeTrue()
        ->and(app(PortfolioReader::class)->for($user)['by_currency'][0]['expense_month_minor'])->toBe(0);
});

test('buying an investment is not an expense and selling more than holdings is refused', function () {
    $user = User::factory()->create();
    $broker = atlasAccount($user, 'DEGIRO', '1.000,00', 'investment');

    atlasMove($user, $broker, JournalEntryType::InvestmentBuy, '200,00', 'ETF');

    $snapshot = app(PortfolioReader::class)->for($user)['by_currency'][0];

    expect(app(BalanceReader::class)->accountMinor($broker->fresh()))->toBe(80000)
        ->and($snapshot['invested_minor'])->toBe(20000)
        ->and($snapshot['net_worth_minor'])->toBe(100000)
        ->and($snapshot['expense_month_minor'])->toBe(0)
        ->and($snapshot['liquidity_minor'])->toBe(0);

    expect(fn () => atlasMove($user, $broker, JournalEntryType::InvestmentSell, '250,00', 'Vendita eccessiva'))
        ->toThrow(InsufficientHoldingsException::class);

    atlasMove($user, $broker, JournalEntryType::InvestmentSell, '50,00', 'Vendita parziale');

    $after = app(PortfolioReader::class)->for($user)['by_currency'][0];

    expect(app(BalanceReader::class)->accountMinor($broker->fresh()))->toBe(85000)
        ->and($after['invested_minor'])->toBe(15000)
        ->and($after['net_worth_minor'])->toBe(100000)
        ->and($after['income_month_minor'])->toBe(0);
});

test('reconciliation adjustment changes the ledger only when requested', function () {
    $user = User::factory()->create();
    $account = atlasAccount($user, 'Contanti', '100,00', 'cash');

    app(ReconcileAccount::class)->handle($user, $account, '90,00', now()->toDateString(), true, 'Mancano 10 euro');

    expect(app(BalanceReader::class)->accountMinor($account->fresh()))->toBe(9000);
});

test('unbalanced journal lines are rejected', function () {
    $user = User::factory()->create();
    $account = atlasAccount($user, 'Intesa', '10,00');
    $ledgerId = $account->ledgerAccount->id;
    $before = JournalEntry::query()->count();

    expect(fn () => app(JournalPoster::class)->post($user, [
        'type' => JournalEntryType::Expense,
        'origin' => JournalOrigin::Manual,
        'occurred_on' => now()->toDateString(),
        'booked_on' => now()->toDateString(),
        'description' => 'Rotta',
        'currency' => 'EUR',
    ], [
        ['ledger_account_id' => $ledgerId, 'side' => JournalSide::Debit, 'amount_minor' => 100, 'currency' => 'EUR'],
        ['ledger_account_id' => $ledgerId, 'side' => JournalSide::Credit, 'amount_minor' => 90, 'currency' => 'EUR'],
    ]))->toThrow(UnbalancedJournalException::class);

    expect(JournalEntry::query()->count())->toBe($before);
});

test('another user cannot read or post on a foreign account', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $account = atlasAccount($owner, 'Intesa', '200,00');

    expect($intruder->can('view', $account))->toBeFalse();

    $this->actingAs($intruder)
        ->get(route('accounts.show', $account))
        ->assertNotFound();

    $this->actingAs($intruder)
        ->post(route('movements.store'), [
            'type' => 'expense',
            'amount' => '10,00',
            'occurred_on' => now()->toDateString(),
            'description' => 'Non autorizzata',
            'account_id' => $account->id,
        ])
        ->assertSessionHasErrors('account_id');

    expect(app(BalanceReader::class)->accountMinor($account->fresh()))->toBe(20000);
});

test('the assistant refuses ambiguous account names', function () {
    $user = User::factory()->create();
    atlasAccount($user, 'Intesa Sanpaolo', '100,00');
    atlasAccount($user, 'Intesa Risparmio', '40,00', 'savings');

    expect(fn () => app(FinanceAssistant::class)->spend($user, '5,00', 'Bar', 'Intesa'))
        ->toThrow(AmbiguousTargetException::class);
});

test('the assistant records a uniquely matched account from telegram origin', function () {
    $user = User::factory()->create();
    $account = atlasAccount($user, 'HYPE', '30,00', 'card');

    $entry = app(FinanceAssistant::class)->spend($user, '4,50', 'Caffè', 'HYPE');

    expect($entry->origin)->toBe(JournalOrigin::Telegram)
        ->and(app(BalanceReader::class)->accountMinor($account->fresh()))->toBe(2550);
});

test('ledger integrity command passes after mixed postings', function () {
    $user = User::factory()->create();
    $account = atlasAccount($user, 'Intesa', '500,00');
    atlasMove($user, $account, JournalEntryType::Expense, '20,00', 'Spesa');

    $this->artisan('atlas:ledger-check')->assertSuccessful();
});
