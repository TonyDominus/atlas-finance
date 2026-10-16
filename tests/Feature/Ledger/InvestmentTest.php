<?php

use App\Actions\Accounts\UpdateAccount;
use App\Actions\Investments\RecordPrice;
use App\Actions\Investments\SaveInstrument;
use App\Actions\Investments\TradeBook;
use App\Actions\Movements\VoidMovement;
use App\Enums\LedgerAccountType;
use App\Models\Instrument;
use App\Models\JournalEntry;
use App\Models\SecurityTrade;
use App\Models\User;
use App\Services\Forecast\CashFlowForecast;
use App\Services\Investments\PositionReader;
use App\Services\Ledger\BalanceReader;
use App\Services\Ledger\PortfolioReader;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

function atlasInstrument(User $user, string $symbol = 'VWCE', string $currency = 'EUR'): Instrument
{
    return app(SaveInstrument::class)->handle($user, [
        'symbol' => $symbol,
        'name' => 'Vanguard FTSE All-World',
        'kind' => 'etf',
        'currency' => $currency,
        'quote_symbol' => $symbol.'.DEX',
        'watch' => true,
    ]);
}

/**
 * @param  array<string, mixed>  $extra
 */
function atlasTrade(User $user, Instrument $instrument, int $accountId, string $side, string $quantity, string $amount, array $extra = []): SecurityTrade
{
    return app(TradeBook::class)->record($user, [
        'side' => $side,
        'instrument_id' => $instrument->id,
        'account_id' => $accountId,
        'traded_on' => now()->toDateString(),
        'quantity' => $quantity,
        'amount' => $amount,
        ...$extra,
    ]);
}

function atlasEur(User $user): array
{
    return collect(app(PortfolioReader::class)->for($user)['by_currency'])->firstWhere('currency', 'EUR');
}

test('savings are parked: in net worth, outside liquidity and the cash forecast', function () {
    $user = User::factory()->create();
    atlasAccount($user, 'Intesa', '1.000,00');
    atlasAccount($user, 'Conto deposito', '5.000,00', 'savings');

    $eur = atlasEur($user);
    $forecast = app(CashFlowForecast::class)->project($user, now()->addDays(30)->toDateString());

    expect($eur['liquidity_minor'])->toBe(100000)
        ->and($eur['parked_minor'])->toBe(500000)
        ->and($eur['net_worth_minor'])->toBe(600000)
        ->and($forecast['currencies'][0]['opening'])->toBe('1.000,00 €');
});

test('a checking account can be moved to the parked section', function () {
    $user = User::factory()->create();
    $reserve = atlasAccount($user, 'Fondo emergenze', '2.000,00');

    app(UpdateAccount::class)->handle($user, $reserve, [
        'name' => 'Fondo emergenze',
        'type' => 'checking',
        'wealth_bucket' => 'parked',
        'color' => '#115e59',
        'icon' => 'piggy',
        'status' => 'active',
        'include_in_net_worth' => true,
    ]);

    $eur = atlasEur($user);

    expect($eur['liquidity_minor'])->toBe(0)
        ->and($eur['parked_minor'])->toBe(200000);
});

test('a buy moves cash into investments at cost, with the fee as a separate bank fee', function () {
    $user = User::factory()->create();
    $broker = atlasAccount($user, 'Directa', '2.000,00', 'investment');
    $etf = atlasInstrument($user);

    atlasTrade($user, $etf, $broker->id, 'buy', '10', '1.000,00', ['fee' => '2,00']);

    $balances = app(BalanceReader::class);
    $eur = atlasEur($user);
    $position = app(PositionReader::class)->position($user, $etf->id, $broker->id);
    $expenses = $balances->activityByCurrency($user->id, LedgerAccountType::Expense, now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());

    expect($balances->accountMinor($broker->fresh()))->toBe(99800)
        ->and($eur['invested_minor'])->toBe(100000)
        ->and($eur['investment_cash_minor'])->toBe(99800)
        ->and($eur['investments_total_minor'])->toBe(199800)
        ->and($eur['net_worth_minor'])->toBe(199800)
        ->and($expenses['EUR'])->toBe(200)
        ->and($position['quantity_units'])->toBe(10_000_000)
        ->and($position['cost_minor'])->toBe(100000);
});

test('a sell removes average cost and books the gain outside monthly income', function () {
    $user = User::factory()->create();
    $broker = atlasAccount($user, 'Directa', '2.000,00', 'investment');
    $etf = atlasInstrument($user);

    atlasTrade($user, $etf, $broker->id, 'buy', '10', '1.000,00');
    atlasTrade($user, $etf, $broker->id, 'sell', '4', '480,00');

    $balances = app(BalanceReader::class);
    $eur = atlasEur($user);
    $book = app(PositionReader::class)->portfolio($user);
    $income = $balances->activityByCurrency($user->id, LedgerAccountType::Income, now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());

    expect($balances->accountMinor($broker->fresh()))->toBe(148000)
        ->and($eur['invested_minor'])->toBe(60000)
        ->and($eur['net_worth_minor'])->toBe(208000)
        ->and($eur['income_month_minor'])->toBe(0)
        ->and($income)->toBe([])
        ->and($book['positions'][0]['quantity'])->toBe('6')
        ->and($book['positions'][0]['cost_minor'])->toBe(60000)
        ->and($book['by_currency'][0]['realized_minor'])->toBe(8000);

    $this->artisan('atlas:ledger-check')->assertSuccessful();
});

test('a sell at a loss lowers the realized result', function () {
    $user = User::factory()->create();
    $broker = atlasAccount($user, 'Directa', '1.000,00', 'investment');
    $etf = atlasInstrument($user);

    atlasTrade($user, $etf, $broker->id, 'buy', '10', '1.000,00');
    atlasTrade($user, $etf, $broker->id, 'sell', '10', '900,00');

    $book = app(PositionReader::class)->portfolio($user);

    expect($book['positions'])->toBe([])
        ->and($book['by_currency'][0]['realized_minor'])->toBe(-10000)
        ->and(atlasEur($user)['net_worth_minor'])->toBe(90000);
});

test('fractional quantities keep exact cost with half up rounding', function () {
    $user = User::factory()->create();
    $broker = atlasAccount($user, 'Directa', '100,00', 'investment');
    $etf = atlasInstrument($user);

    atlasTrade($user, $etf, $broker->id, 'buy', '0,5', '50,01');
    $sell = atlasTrade($user, $etf, $broker->id, 'sell', '0,25', '26,00');

    expect($sell->cost_minor)->toBe(2501)
        ->and(app(PositionReader::class)->position($user, $etf->id, $broker->id))
        ->toMatchArray(['quantity_units' => 250_000, 'cost_minor' => 2500]);
});

test('selling more than held is refused and nothing is posted', function () {
    $user = User::factory()->create();
    $broker = atlasAccount($user, 'Directa', '1.000,00', 'investment');
    $etf = atlasInstrument($user);
    atlasTrade($user, $etf, $broker->id, 'buy', '2', '200,00');
    $entries = JournalEntry::query()->where('user_id', $user->id)->count();

    expect(fn () => atlasTrade($user, $etf, $broker->id, 'sell', '3', '300,00'))
        ->toThrow(ValidationException::class);

    expect(JournalEntry::query()->where('user_id', $user->id)->count())->toBe($entries);
});

test('an instrument in another currency cannot settle on a euro account', function () {
    $user = User::factory()->create();
    $broker = atlasAccount($user, 'Directa', '1.000,00', 'investment');
    $apple = atlasInstrument($user, 'AAPL', 'USD');

    expect(fn () => atlasTrade($user, $apple, $broker->id, 'buy', '1', '200,00'))
        ->toThrow(ValidationException::class);
});

test('only the latest trade can be voided, and only from the investments book', function () {
    $user = User::factory()->create();
    $broker = atlasAccount($user, 'Directa', '1.000,00', 'investment');
    $etf = atlasInstrument($user);
    $buy = atlasTrade($user, $etf, $broker->id, 'buy', '5', '500,00', ['fee' => '1,00']);
    $sell = atlasTrade($user, $etf, $broker->id, 'sell', '2', '210,00');
    $book = app(TradeBook::class);

    expect(fn () => $book->void($user, $buy, 'errore'))->toThrow(ValidationException::class)
        ->and(fn () => app(VoidMovement::class)->handle($user, $sell->entry, 'errore'))->toThrow(ValidationException::class);

    $book->void($user, $sell, 'errore');
    $book->void($user, $buy->fresh(), 'errore');

    expect(app(BalanceReader::class)->accountMinor($broker->fresh()))->toBe(100000)
        ->and(atlasEur($user)['invested_minor'])->toBe(0)
        ->and(app(PositionReader::class)->position($user, $etf->id, $broker->id)['quantity_units'])->toBe(0);
});

test('a manual price values the position without touching the ledger', function () {
    $user = User::factory()->create();
    $broker = atlasAccount($user, 'Directa', '1.000,00', 'investment');
    $etf = atlasInstrument($user);
    atlasTrade($user, $etf, $broker->id, 'buy', '6', '600,00');

    app(RecordPrice::class)->manual($user, $etf, '110,00', now()->toDateString());
    $book = app(PositionReader::class)->portfolio($user);

    expect($book['positions'][0]['value_minor'])->toBe(66000)
        ->and($book['positions'][0]['unrealized_minor'])->toBe(6000)
        ->and($book['positions'][0]['price_source'])->toBe('Inserito a mano')
        ->and(atlasEur($user)['invested_minor'])->toBe(60000);
});

test('quotes from Alpha Vantage are stored with their source and date', function () {
    config(['services.alphavantage.key' => 'test-key']);
    Http::fake([
        'www.alphavantage.co/*' => Http::response([
            'Global Quote' => [
                '01. symbol' => 'VWCE.DEX',
                '05. price' => '118.4200',
                '07. latest trading day' => '2026-10-15',
                '08. previous close' => '117.9000',
            ],
        ]),
    ]);

    $user = User::factory()->create();
    $etf = atlasInstrument($user);
    $result = app(RecordPrice::class)->refresh($user, [$etf]);
    $price = $etf->prices()->first();

    expect($result['updated'])->toBe(['VWCE'])
        ->and($price->source)->toBe('alphavantage')
        ->and($price->priced_on->toDateString())->toBe('2026-10-15')
        ->and($price->price_units)->toBe(118_420_000);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'symbol=VWCE.DEX'));
});

test('without a key no quote is fetched and nothing is invented', function () {
    config(['services.alphavantage.key' => null]);
    Http::fake();

    $user = User::factory()->create();
    $etf = atlasInstrument($user);
    $result = app(RecordPrice::class)->refresh($user, [$etf]);

    expect($result['updated'])->toBe([])
        ->and($etf->prices()->count())->toBe(0);

    Http::assertNothingSent();
});

test('another user cannot touch an instrument or a trade', function () {
    $owner = User::factory()->create();
    $broker = atlasAccount($owner, 'Directa', '1.000,00', 'investment');
    $etf = atlasInstrument($owner);
    $trade = atlasTrade($owner, $etf, $broker->id, 'buy', '1', '100,00');
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->post(route('market.price', $etf), ['price' => '1,00', 'priced_on' => now()->toDateString()])
        ->assertNotFound();

    $this->actingAs($intruder)
        ->post(route('investments.void', $trade))
        ->assertNotFound();

    expect($etf->prices()->count())->toBe(0)
        ->and(app(PositionReader::class)->position($owner, $etf->id, $broker->id)['quantity_units'])->toBe(1_000_000);
});

test('the wealth, investments and market pages render', function () {
    $user = User::factory()->create();
    $broker = atlasAccount($user, 'Directa', '1.000,00', 'investment');
    $etf = atlasInstrument($user);
    atlasTrade($user, $etf, $broker->id, 'buy', '1', '100,00');

    $this->actingAs($user)->get(route('wealth.index'))->assertOk();
    $this->actingAs($user)->get(route('investments.index'))->assertOk();
    $this->actingAs($user)->get(route('market.index'))->assertOk();
});
