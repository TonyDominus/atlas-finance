<?php

namespace App\Http\Controllers;

use App\Actions\Investments\TradeBook;
use App\Enums\AccountStatus;
use App\Http\Controllers\Concerns\OwnsFinancialRecords;
use App\Models\Account;
use App\Models\Instrument;
use App\Models\SecurityTrade;
use App\Services\Investments\PositionReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InvestmentController extends Controller
{
    use OwnsFinancialRecords;

    public function index(Request $request, PositionReader $positions): Response
    {
        $user = $request->user();
        $book = $positions->portfolio($user);

        return Inertia::render('Investments/Index', [
            ...$book,
            'instruments' => Instrument::query()
                ->where('user_id', $user->id)
                ->orderBy('symbol')
                ->get()
                ->map(fn (Instrument $instrument) => [
                    'id' => $instrument->id,
                    'label' => $instrument->symbol.' · '.$instrument->name,
                    'currency' => $instrument->currency,
                ])
                ->all(),
            'accounts' => Account::query()
                ->where('user_id', $user->id)
                ->where('status', AccountStatus::Active)
                ->orderBy('name')
                ->get()
                ->map(fn (Account $account) => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'currency' => $account->currency,
                    'bucket' => $account->bucket()->value,
                ])
                ->all(),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(Request $request, TradeBook $book): RedirectResponse
    {
        $data = $request->validate([
            'side' => ['required', Rule::in(['buy', 'sell'])],
            'instrument_id' => ['required', 'integer'],
            'account_id' => ['required', 'integer'],
            'traded_on' => ['required', 'date', 'before_or_equal:today'],
            'quantity' => ['required', 'string', 'max:32'],
            'amount' => ['required', 'string', 'max:32'],
            'fee' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'traded_on.before_or_equal' => 'Le operazioni su titoli si registrano a data avvenuta.',
        ]);

        $trade = $book->record($request->user(), $data);

        return redirect()
            ->route('investments.index')
            ->with('success', $trade->side->value === 'buy'
                ? 'Acquisto registrato. Il denaro passa dal conto agli investimenti: non è una spesa.'
                : 'Vendita registrata. La differenza dal costo medio va nei risultati da investimenti, non tra le entrate del mese.');
    }

    public function void(Request $request, SecurityTrade $trade, TradeBook $book): RedirectResponse
    {
        $this->owned($request, $trade);
        $book->void($request->user(), $trade, (string) ($request->input('reason') ?: 'Annullata dalla pagina investimenti'));

        return redirect()->route('investments.index')->with('success', 'Operazione annullata con storno. Quantità e costo tornano come prima.');
    }
}
