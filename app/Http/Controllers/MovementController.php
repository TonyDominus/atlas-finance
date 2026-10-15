<?php

namespace App\Http\Controllers;

use App\Actions\Movements\PostForecast;
use App\Actions\Movements\RecordMovement;
use App\Actions\Movements\UpdateMovementDetails;
use App\Actions\Movements\VoidMovement;
use App\Domain\Ledger\LedgerException;
use App\Domain\Money\Money;
use App\Domain\Movements\MovementData;
use App\Enums\AccountStatus;
use App\Enums\JournalEntryStatus;
use App\Enums\JournalEntryType;
use App\Enums\JournalOrigin;
use App\Http\Controllers\Concerns\OwnsFinancialRecords;
use App\Http\Presenters\MovementPresenter;
use App\Http\Requests\StoreMovementRequest;
use App\Http\Requests\UpdateMovementRequest;
use App\Http\Requests\VoidMovementRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Counterparty;
use App\Models\JournalEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class MovementController extends Controller
{
    use OwnsFinancialRecords;

    public function index(Request $request, MovementPresenter $presenter): Response
    {
        $filters = [
            'q' => (string) $request->string('q'),
            'type' => (string) $request->string('type'),
            'account_id' => (string) $request->string('account_id'),
            'category_id' => (string) $request->string('category_id'),
            'counterparty_id' => (string) $request->string('counterparty_id'),
            'from' => (string) $request->string('from'),
            'to' => (string) $request->string('to'),
            'voided' => (string) $request->string('voided'),
        ];

        $movements = JournalEntry::query()
            ->where('user_id', $request->user()->id)
            ->with(['category', 'counterparty', 'lines.ledgerAccount.financialAccount'])
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $query->where(function ($inner) use ($filters) {
                    $inner->where('description', 'like', '%'.$filters['q'].'%')
                        ->orWhere('notes', 'like', '%'.$filters['q'].'%');
                });
            })
            ->when($filters['type'] !== '', fn ($query) => $query->where('type', $filters['type']))
            ->when($filters['category_id'] !== '', fn ($query) => $query->where('category_id', $filters['category_id']))
            ->when($filters['counterparty_id'] !== '', fn ($query) => $query->where('counterparty_id', $filters['counterparty_id']))
            ->when($filters['from'] !== '', fn ($query) => $query->whereDate('booked_on', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn ($query) => $query->whereDate('booked_on', '<=', $filters['to']))
            ->when($filters['voided'] === 'yes', fn ($query) => $query->whereNotNull('voided_at'))
            ->when($filters['voided'] === 'no', fn ($query) => $query->whereNull('voided_at')->where('type', '!=', JournalEntryType::Reversal->value))
            ->when($filters['account_id'] !== '', function ($query) use ($filters) {
                $query->whereHas('lines', fn ($lines) => $lines->whereHas(
                    'ledgerAccount',
                    fn ($ledger) => $ledger->where('financial_account_id', $filters['account_id']),
                ));
            })
            ->orderByDesc('booked_on')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (JournalEntry $entry) => $presenter->summary($entry));

        return Inertia::render('Movements/Index', [
            'movements' => $movements,
            'filters' => $filters,
            'accounts' => $this->accountOptions($request),
            'categories' => $this->categoryOptions($request),
            'counterparties' => $this->counterpartyOptions($request),
            'types' => JournalEntryType::recordableOptions(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Movements/Create', [
            'accounts' => $this->accountOptions($request, true),
            'categories' => $this->categoryOptions($request),
            'counterparties' => $this->counterpartyOptions($request),
            'types' => JournalEntryType::recordableOptions(),
            'prefillAccountId' => $request->integer('account') ?: null,
            'today' => now()->toDateString(),
        ]);
    }

    public function store(StoreMovementRequest $request, RecordMovement $action): RedirectResponse
    {
        try {
            $entry = $action->handle(
                $request->user(),
                $this->movementData($request),
                $this->files($request),
            );
        } catch (LedgerException|InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'amount' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('movements.show', $entry)
            ->with('success', $entry->status === JournalEntryStatus::Forecast
                ? 'Previsione registrata. Il saldo non cambia finché non la contabilizzi.'
                : 'Movimento registrato nel ledger.');
    }

    public function show(Request $request, JournalEntry $movement, MovementPresenter $presenter): Response
    {
        $this->owned($request, $movement);
        $movement->load([
            'lines.ledgerAccount.financialAccount',
            'category',
            'counterparty',
            'tags',
            'attachments',
            'relatedEntries',
        ]);

        return Inertia::render('Movements/Show', [
            'movement' => $presenter->detail($movement),
            'categories' => $this->categoryOptions($request),
            'counterparties' => $this->counterpartyOptions($request),
        ]);
    }

    public function update(UpdateMovementRequest $request, JournalEntry $movement, UpdateMovementDetails $action): RedirectResponse
    {
        $this->owned($request, $movement);

        try {
            $action->handle($request->user(), $movement, $request->validated());
        } catch (LedgerException $exception) {
            throw ValidationException::withMessages(['description' => $exception->getMessage()]);
        }

        return redirect()
            ->route('movements.show', $movement)
            ->with('success', 'Dettagli aggiornati. Importi e conti restano nello storico.');
    }

    public function void(VoidMovementRequest $request, JournalEntry $movement, VoidMovement $action): RedirectResponse
    {
        $this->owned($request, $movement);

        try {
            $action->handle($request->user(), $movement, $request->string('reason')->toString());
        } catch (LedgerException $exception) {
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }

        return redirect()
            ->route('movements.show', $movement)
            ->with('success', 'Movimento annullato con storno. Lo storico resta verificabile.');
    }

    public function post(Request $request, JournalEntry $movement, PostForecast $action): RedirectResponse
    {
        $this->owned($request, $movement);
        $action->handle($request->user(), $movement);

        return redirect()
            ->route('movements.show', $movement)
            ->with('success', 'Previsione contabilizzata. Il saldo ora la include.');
    }

    private function movementData(StoreMovementRequest $request): MovementData
    {
        $account = Account::query()
            ->where('user_id', $request->user()->id)
            ->find($request->integer('account_id'));

        if (! $account) {
            throw ValidationException::withMessages(['account_id' => 'Conto non trovato.']);
        }

        try {
            $amount = Money::parse($request->string('amount')->toString(), $account->currency);
            $fee = null;

            if ($request->input('type') === JournalEntryType::Transfer->value && $request->filled('fee_amount')) {
                $fee = Money::parse($request->string('fee_amount')->toString(), $account->currency);
                $fee = $fee->isZero() ? null : $fee;
            }
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
        }

        return new MovementData(
            type: JournalEntryType::from($request->string('type')->toString()),
            amount: $amount,
            occurredOn: $request->string('occurred_on')->toString(),
            bookedOn: $request->string('booked_on')->toString(),
            description: $request->string('description')->toString(),
            accountId: $account->id,
            destinationAccountId: $request->integer('destination_account_id') ?: null,
            fee: $fee,
            categoryId: $request->integer('category_id') ?: null,
            counterpartyId: $request->integer('counterparty_id') ?: null,
            counterpartyName: $request->input('counterparty_name'),
            notes: $request->input('notes'),
            tags: $request->input('tags', []),
            externalId: $request->input('external_id'),
            origin: JournalOrigin::Manual,
            status: $request->boolean('as_forecast') ? JournalEntryStatus::Forecast : JournalEntryStatus::Posted,
        );
    }

    /**
     * @return list<UploadedFile>
     */
    private function files(Request $request): array
    {
        $files = $request->file('attachments', []);

        if ($files instanceof UploadedFile) {
            return [$files];
        }

        return array_values(array_filter($files ?? []));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function accountOptions(Request $request, bool $activeOnly = false): array
    {
        return Account::query()
            ->where('user_id', $request->user()->id)
            ->when($activeOnly, fn ($query) => $query->where('status', AccountStatus::Active))
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'currency' => $account->currency,
                'type_label' => $account->type->label(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function categoryOptions(Request $request): array
    {
        return Category::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'kind' => $category->kind->value,
                'parent_id' => $category->parent_id,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function counterpartyOptions(Request $request): array
    {
        return Counterparty::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Counterparty $counterparty) => [
                'id' => $counterparty->id,
                'name' => $counterparty->name,
                'type_label' => $counterparty->type->label(),
            ])
            ->all();
    }
}
