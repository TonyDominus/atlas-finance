<?php

use App\Actions\Budget\SaveBudget;
use App\Actions\Loans\LoanBook;
use App\Actions\Movements\PostForecast;
use App\Actions\Movements\RecordMovement;
use App\Actions\Recurrence\SaveRecurringRule;
use App\Actions\Recurrence\SettleOccurrence;
use App\Domain\Money\Money;
use App\Domain\Movements\MovementData;
use App\Enums\JournalEntryStatus;
use App\Enums\JournalEntryType;
use App\Enums\JournalOrigin;
use App\Models\Category;
use App\Models\JournalEntry;
use App\Models\RecurringOccurrence;
use App\Models\User;
use App\Services\Analytics\AnalyticsReader;
use App\Services\Budget\BudgetReader;
use App\Services\Forecast\CashFlowForecast;
use App\Services\Ledger\BalanceReader;
use App\Services\Ledger\PortfolioReader;
use App\Services\Loans\LoanPosition;
use App\Services\Loans\LoanReader;
use Illuminate\Validation\ValidationException;

test('an account fee is forecast and is not posted when due', function () {
    $user = User::factory()->create();
    $account = atlasAccount($user, 'HYPE', '100,00', 'card');

    $rule = app(SaveRecurringRule::class)->create($user, [
        'description' => 'Canone HYPE',
        'kind' => 'fee',
        'purpose' => 'account_fee',
        'amount' => '2,90',
        'account_id' => $account->id,
        'frequency' => 'monthly',
        'interval' => 1,
        'starts_on' => now()->toDateString(),
    ]);

    expect(RecurringOccurrence::query()->where('recurring_rule_id', $rule->id)->where('status', 'forecast')->exists())->toBeTrue()
        ->and(JournalEntry::query()->where('type', 'fee')->count())->toBe(0)
        ->and(app(BalanceReader::class)->accountMinor($account->fresh()))->toBe(10000)
        ->and(app(AnalyticsReader::class)->bankFees($user)['annualized_minor'])->toBe(3480);
});

test('a forecast occurrence stays out of the ledger until it is linked once', function () {
    $user = User::factory()->create();
    $account = atlasAccount($user, 'Intesa', '500,00');
    $rule = app(SaveRecurringRule::class)->create($user, [
        'description' => 'Netflix',
        'kind' => 'expense',
        'amount' => '15,99',
        'account_id' => $account->id,
        'frequency' => 'monthly',
        'interval' => 1,
        'starts_on' => now()->toDateString(),
        'max_occurrences' => 2,
    ]);

    $occurrence = RecurringOccurrence::query()->where('recurring_rule_id', $rule->id)->orderBy('due_on')->first();

    expect($occurrence->status->value)->toBe('forecast')
        ->and(app(BalanceReader::class)->accountMinor($account->fresh()))->toBe(50000);

    $entry = app(SettleOccurrence::class)->realize($user, $occurrence);

    expect($entry->type)->toBe(JournalEntryType::Expense)
        ->and($occurrence->fresh()->status->value)->toBe('linked')
        ->and(app(BalanceReader::class)->accountMinor($account->fresh()))->toBe(48401);

    expect(fn () => app(SettleOccurrence::class)->realize($user, $occurrence->fresh()))
        ->toThrow(ValidationException::class);

    expect(JournalEntry::query()->where('type', 'expense')->count())->toBe(1);
});

test('linking an existing movement does not post it again', function () {
    $user = User::factory()->create();
    $account = atlasAccount($user, 'Intesa', '100,00');
    $movement = atlasMove($user, $account, JournalEntryType::Expense, '10,00', 'Affitto');
    $rule = app(SaveRecurringRule::class)->create($user, [
        'description' => 'Affitto',
        'kind' => 'expense',
        'amount' => '10,00',
        'account_id' => $account->id,
        'frequency' => 'monthly',
        'starts_on' => now()->toDateString(),
    ]);
    $occurrence = RecurringOccurrence::query()->where('recurring_rule_id', $rule->id)->orderBy('due_on')->first();

    app(SettleOccurrence::class)->link($user, $occurrence, $movement);

    expect($occurrence->fresh()->journal_entry_id)->toBe($movement->id)
        ->and(app(BalanceReader::class)->accountMinor($account->fresh()))->toBe(9000)
        ->and(JournalEntry::query()->where('type', 'expense')->count())->toBe(1);
});

test('a private loan repaid from several accounts does not become income', function () {
    $user = User::factory()->create();
    $intesa = atlasAccount($user, 'Intesa Sanpaolo', '1.000,00');
    $paypal = atlasAccount($user, 'PayPal', '0,00', 'wallet');
    $hype = atlasAccount($user, 'HYPE', '0,00', 'card');
    $cash = atlasAccount($user, 'Contanti', '0,00', 'cash');
    $unicredit = atlasAccount($user, 'UniCredit', '0,00');
    $book = app(LoanBook::class);

    $loan = $book->open($user, [
        'direction' => 'given',
        'counterparty_name' => 'Marco',
        'amount' => '200,00',
        'account_id' => $intesa->id,
        'opened_on' => now()->toDateString(),
        'description' => 'Prestito a Marco',
    ]);

    $balances = app(BalanceReader::class);
    $snapshot = app(PortfolioReader::class)->for($user)['by_currency'][0];

    expect($balances->accountMinor($intesa->fresh()))->toBe(80000)
        ->and($snapshot['income_month_minor'])->toBe(0)
        ->and($snapshot['expense_month_minor'])->toBe(0)
        ->and($snapshot['net_worth_minor'])->toBe(100000)
        ->and(app(LoanPosition::class)->status($loan))->toBe('open');

    foreach ([
        [$paypal, '50,00'],
        [$hype, '70,00'],
        [$cash, '30,00'],
        [$unicredit, '50,00'],
    ] as [$account, $amount]) {
        $book->repay($user, [
            'occurred_on' => now()->toDateString(),
            'kind' => 'cash',
            'account_id' => $account->id,
            'allocations' => [[
                'loan_id' => $loan->id,
                'principal' => $amount,
                'interest' => '0,00',
            ]],
        ]);
    }

    $closed = app(LoanReader::class)->detail($loan->fresh());
    $after = app(PortfolioReader::class)->for($user)['by_currency'][0];

    expect($closed['status'])->toBe('closed')
        ->and($closed['residual_minor'])->toBe(0)
        ->and($balances->accountMinor($paypal->fresh()))->toBe(5000)
        ->and($balances->accountMinor($hype->fresh()))->toBe(7000)
        ->and($balances->accountMinor($cash->fresh()))->toBe(3000)
        ->and($balances->accountMinor($unicredit->fresh()))->toBe(5000)
        ->and($after['income_month_minor'])->toBe(0)
        ->and($after['expense_month_minor'])->toBe(0)
        ->and($after['net_worth_minor'])->toBe(100000);
});

test('principal cannot exceed the residual unless the surplus is explicitly interest', function () {
    $user = User::factory()->create();
    $account = atlasAccount($user, 'Intesa', '1.000,00');
    $book = app(LoanBook::class);
    $loan = $book->open($user, [
        'direction' => 'given',
        'counterparty_name' => 'Luca',
        'amount' => '200,00',
        'account_id' => $account->id,
        'opened_on' => now()->toDateString(),
        'description' => 'Prestito',
    ]);

    expect(fn () => $book->repay($user, [
        'occurred_on' => now()->toDateString(),
        'kind' => 'cash',
        'account_id' => $account->id,
        'allocations' => [[
            'loan_id' => $loan->id,
            'principal' => '250,00',
            'interest' => '0,00',
        ]],
    ]))->toThrow(ValidationException::class);

    expect(app(BalanceReader::class)->accountMinor($account->fresh()))->toBe(80000)
        ->and(app(LoanPosition::class)->residual($loan))->toBe(20000);

    $book->repay($user, [
        'occurred_on' => now()->toDateString(),
        'kind' => 'cash',
        'account_id' => $account->id,
        'surplus_as' => 'interest',
        'allocations' => [[
            'loan_id' => $loan->id,
            'principal' => '250,00',
            'interest' => '0,00',
        ]],
    ]);

    $snapshot = app(PortfolioReader::class)->for($user)['by_currency'][0];

    expect(app(LoanPosition::class)->status($loan->fresh()))->toBe('closed')
        ->and(app(LoanPosition::class)->residual($loan->fresh()))->toBe(0)
        ->and(app(BalanceReader::class)->accountMinor($account->fresh()))->toBe(105000)
        ->and($snapshot['income_month_minor'])->toBe(5000)
        ->and($snapshot['expense_month_minor'])->toBe(0);
});

test('another user cannot open a loan', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = atlasAccount($owner, 'Intesa', '100,00');
    $loan = app(LoanBook::class)->open($owner, [
        'direction' => 'given',
        'counterparty_name' => 'Marco',
        'amount' => '20,00',
        'account_id' => $account->id,
        'opened_on' => now()->toDateString(),
        'description' => 'Prestito',
    ]);

    $this->actingAs($other)->get(route('loans.show', $loan))->assertNotFound();
});

test('budgets compare assigned and used amounts for the month', function () {
    $user = User::factory()->create();
    $account = atlasAccount($user, 'Intesa', '500,00');
    $category = Category::query()->where('user_id', $user->id)->where('slug', 'ristoranti')->first();

    app(RecordMovement::class)->handle($user, new MovementData(
        type: JournalEntryType::Expense,
        amount: Money::parse('150,00'),
        occurredOn: now()->toDateString(),
        bookedOn: now()->toDateString(),
        description: 'Cena',
        accountId: $account->id,
        destinationAccountId: null,
        fee: null,
        categoryId: $category->id,
        counterpartyId: null,
        counterpartyName: null,
        notes: null,
        tags: [],
        externalId: null,
        origin: JournalOrigin::Manual,
    ));

    app(SaveBudget::class)->handle($user, $category->id, '120,00');
    $row = app(BudgetReader::class)->forMonth($user, now()->format('Y-m'))[0];

    expect($row['amount_minor'])->toBe(12000)
        ->and($row['used_minor'])->toBe(15000)
        ->and($row['remaining_minor'])->toBe(-3000)
        ->and($row['percent'])->toBe(125)
        ->and($row['over'])->toBeTrue()
        ->and(app(BudgetReader::class)->historyIsSufficient($user))->toBeFalse();
});

test('cash forecast includes a recurrence and ignores an open loan due date', function () {
    $user = User::factory()->create();
    $account = atlasAccount($user, 'Intesa', '1.000,00');
    $tomorrow = now()->addDay()->toDateString();

    app(SaveRecurringRule::class)->create($user, [
        'description' => 'Mutuo',
        'kind' => 'expense',
        'amount' => '100,00',
        'account_id' => $account->id,
        'frequency' => 'monthly',
        'starts_on' => $tomorrow,
        'max_occurrences' => 1,
    ]);

    app(LoanBook::class)->open($user, [
        'direction' => 'given',
        'counterparty_name' => 'Anna',
        'amount' => '200,00',
        'account_id' => $account->id,
        'opened_on' => now()->toDateString(),
        'due_on' => $tomorrow,
        'description' => 'Prestito non previsto nel cash flow',
    ]);

    $forecast = app(CashFlowForecast::class)->project($user, $tomorrow);
    $currency = $forecast['currencies'][0];

    expect($forecast['estimate'])->toBeTrue()
        ->and($currency['opening_minor'])->toBe(80000)
        ->and($currency['closing_minor'])->toBe(70000)
        ->and(collect($currency['events'])->pluck('description')->all())->toBe(['Mutuo'])
        ->and(JournalEntry::query()->where('description', 'Mutuo')->exists())->toBeFalse();
});

test('a forecast movement does not change the balance until it is posted', function () {
    $user = User::factory()->create();
    $account = atlasAccount($user, 'Intesa', '80,00');

    $entry = app(RecordMovement::class)->handle($user, new MovementData(
        type: JournalEntryType::Expense,
        amount: Money::parse('12,50'),
        occurredOn: now()->toDateString(),
        bookedOn: now()->toDateString(),
        description: 'Bar',
        accountId: $account->id,
        destinationAccountId: null,
        fee: null,
        categoryId: null,
        counterpartyId: null,
        counterpartyName: null,
        notes: null,
        tags: [],
        externalId: null,
        origin: JournalOrigin::Manual,
        status: JournalEntryStatus::Forecast,
    ));

    expect(app(BalanceReader::class)->accountMinor($account->fresh()))->toBe(8000);

    app(PostForecast::class)->handle($user, $entry);

    expect($entry->fresh()->status)->toBe(JournalEntryStatus::Posted)
        ->and(app(BalanceReader::class)->accountMinor($account->fresh()))->toBe(6750);
});
