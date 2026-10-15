<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\OwnsFinancialRecords;
use App\Http\Requests\StoreCounterpartyRequest;
use App\Http\Requests\UpdateCounterpartyRequest;
use App\Models\Counterparty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CounterpartyController extends Controller
{
    use OwnsFinancialRecords;

    public function index(Request $request): Response
    {
        $counterparties = Counterparty::query()
            ->where('user_id', $request->user()->id)
            ->withCount('journalEntries')
            ->orderBy('name')
            ->get()
            ->map(fn (Counterparty $counterparty) => [
                'id' => $counterparty->id,
                'name' => $counterparty->name,
                'type' => $counterparty->type->value,
                'type_label' => $counterparty->type->label(),
                'notes' => $counterparty->notes,
                'movements_count' => $counterparty->journal_entries_count,
            ]);

        return Inertia::render('Counterparties/Index', [
            'counterparties' => $counterparties,
            'types' => \App\Enums\CounterpartyType::options(),
        ]);
    }

    public function store(StoreCounterpartyRequest $request): RedirectResponse
    {
        Counterparty::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('counterparties.index')->with('success', 'Controparte aggiunta.');
    }

    public function update(UpdateCounterpartyRequest $request, Counterparty $counterparty): RedirectResponse
    {
        $this->owned($request, $counterparty);
        $counterparty->update($request->validated());

        return redirect()->route('counterparties.index')->with('success', 'Controparte aggiornata.');
    }

    public function destroy(Request $request, Counterparty $counterparty): RedirectResponse
    {
        $this->owned($request, $counterparty);

        if ($counterparty->journalEntries()->exists()) {
            return redirect()
                ->route('counterparties.index')
                ->with('error', 'La controparte ha movimenti collegati e non può essere eliminata.');
        }

        $counterparty->delete();

        return redirect()->route('counterparties.index')->with('success', 'Controparte eliminata.');
    }
}
