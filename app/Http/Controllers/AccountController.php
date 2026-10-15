<?php

namespace App\Http\Controllers;

use App\Actions\Accounts\CreateAccount;
use App\Actions\Accounts\ReconcileAccount;
use App\Actions\Accounts\UpdateAccount;
use App\Domain\Money\Currency;
use App\Domain\Money\Money;
use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Http\Controllers\Concerns\OwnsFinancialRecords;
use App\Http\Presenters\MovementPresenter;
use App\Http\Requests\ReconcileAccountRequest;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Models\RecurringRule;
use App\Services\Recurrence\OccurrenceProjector;
use App\Services\Recurrence\RecurrenceReader;
use App\Services\Ledger\BalanceReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    use OwnsFinancialRecords;

    public function index(Request $request): Response
    {
        $accounts = Account::query()
            ->where('user_id', $request->user()->id)
            ->with('institution')
            ->orderBy('name')
            ->get();

        $balances = app(BalanceReader::class)->accountBalanceMap($request->user()->id);

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts->map(fn (Account $account) => $this->card($account, $balances[$account->id] ?? 0))->all(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Accounts/Form', [
            'account' => null,
            ...$this->options(),
        ]);
    }

    public function store(StoreAccountRequest $request, CreateAccount $action): RedirectResponse
    {
        $account = $action->handle($request->user(), $request->validated());

        return redirect()
            ->route('accounts.show', $account)
            ->with('success', 'Conto creato. Il saldo parte dal movimento di apertura.');
    }

    public function show(Request $request, Account $account, BalanceReader $balances, MovementPresenter $presenter, RecurrenceReader $recurrences, OccurrenceProjector $projector): Response
    {
        $this->owned($request, $account);
        $account->load('institution');

        $movements = JournalEntry::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('lines', fn ($query) => $query->whereHas(
                'ledgerAccount',
                fn ($ledger) => $ledger->where('financial_account_id', $account->id),
            ))
            ->with(['category', 'counterparty', 'lines.ledgerAccount.financialAccount'])
            ->orderByDesc('booked_on')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (JournalEntry $entry) => $presenter->summary($entry));

        $minor = $balances->accountMinor($account);
        $latest = $account->reconciliations()->latest('reconciled_on')->latest('id')->first();

        $feeRules = RecurringRule::query()
            ->where('user_id', $request->user()->id)
            ->where('account_id', $account->id)
            ->where('purpose', 'account_fee')
            ->with(['account', 'category'])
            ->orderBy('description')
            ->get();

        foreach ($feeRules as $rule) {
            if ($rule->status->value === 'active') {
                $projector->sync($rule);
            }
        }

        $fees = $feeRules->map(fn (RecurringRule $rule) => $recurrences->rule($rule));

        return Inertia::render('Accounts/Show', [
            'account' => [
                ...$this->card($account, $minor),
                'notes' => $account->notes,
                'opening_balance' => Money::ofMinor($account->opening_balance_minor, $account->currency)->format(),
                'opening_balance_on' => $account->opening_balance_on->toDateString(),
            ],
            'movements' => $movements,
            'today' => now()->toDateString(),
            'fees' => $fees,
            'reconciliation' => $latest ? [
                'reconciled_on' => $latest->reconciled_on->toDateString(),
                'statement' => Money::ofMinor($latest->statement_balance_minor, $account->currency)->format(),
                'computed' => Money::ofMinor($latest->computed_balance_minor, $account->currency)->format(),
                'difference' => Money::ofMinor($latest->difference_minor, $account->currency)->format(),
                'adjusted' => $latest->journal_entry_id !== null,
                'notes' => $latest->notes,
            ] : null,
        ]);
    }

    public function edit(Request $request, Account $account): Response
    {
        $this->owned($request, $account);
        $account->load('institution');

        return Inertia::render('Accounts/Form', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'institution' => $account->institution?->name,
                'type' => $account->type->value,
                'currency' => $account->currency,
                'color' => $account->color,
                'icon' => $account->icon,
                'status' => $account->status->value,
                'include_in_net_worth' => $account->include_in_net_worth,
                'notes' => $account->notes,
                'opening_balance' => Money::ofMinor($account->opening_balance_minor, $account->currency)->format(),
                'opening_balance_on' => $account->opening_balance_on->toDateString(),
            ],
            ...$this->options(),
        ]);
    }

    public function update(UpdateAccountRequest $request, Account $account, UpdateAccount $action): RedirectResponse
    {
        $this->owned($request, $account);
        $action->handle($request->user(), $account, $request->validated());

        return redirect()
            ->route('accounts.show', $account)
            ->with('success', 'Conto aggiornato.');
    }

    public function reconcile(ReconcileAccountRequest $request, Account $account, ReconcileAccount $action): RedirectResponse
    {
        $this->owned($request, $account);

        $record = $action->handle(
            $request->user(),
            $account,
            $request->string('statement_balance')->toString(),
            $request->string('reconciled_on')->toString(),
            $request->boolean('post_adjustment'),
            $request->input('notes'),
        );

        $message = $record->difference_minor === 0
            ? 'Saldo riconciliato: coincide con il ledger.'
            : ($record->journal_entry_id
                ? 'Rettifica registrata. Il saldo di libro ora segue l\'estratto.'
                : 'Riconciliazione annotata, senza modificare il ledger.');

        return redirect()->route('accounts.show', $account)->with('success', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function card(Account $account, int $minor): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'institution' => $account->institution?->name,
            'type' => $account->type->value,
            'type_label' => $account->type->label(),
            'currency' => $account->currency,
            'color' => $account->color,
            'icon' => $account->icon,
            'status' => $account->status->value,
            'status_label' => $account->status->label(),
            'include_in_net_worth' => $account->include_in_net_worth,
            'balance' => Money::ofMinor($minor, $account->currency)->format(),
            'balance_minor' => $minor,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'types' => AccountType::options(),
            'statuses' => AccountStatus::options(),
            'currencies' => Currency::codes(),
            'icons' => [
                ['value' => 'landmark', 'label' => 'Banca'],
                ['value' => 'card', 'label' => 'Carta'],
                ['value' => 'wallet', 'label' => 'Wallet'],
                ['value' => 'cash', 'label' => 'Contanti'],
                ['value' => 'chart', 'label' => 'Investimenti'],
                ['value' => 'piggy', 'label' => 'Risparmio'],
            ],
            'today' => now()->toDateString(),
        ];
    }
}
