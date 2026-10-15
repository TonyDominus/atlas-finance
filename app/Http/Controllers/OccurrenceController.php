<?php

namespace App\Http\Controllers;

use App\Actions\Recurrence\SettleOccurrence;
use App\Http\Controllers\Concerns\OwnsFinancialRecords;
use App\Models\JournalEntry;
use App\Models\RecurringOccurrence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OccurrenceController extends Controller
{
    use OwnsFinancialRecords;

    public function realize(Request $request, RecurringOccurrence $occurrence, SettleOccurrence $action): RedirectResponse
    {
        $this->owned($request, $occurrence);
        $entry = $action->realize($request->user(), $occurrence);

        return redirect()
            ->route('movements.show', $entry)
            ->with('success', 'Occorrenza contabilizzata. Non risulta più tra le previsioni.');
    }

    public function link(Request $request, RecurringOccurrence $occurrence, SettleOccurrence $action): RedirectResponse
    {
        $this->owned($request, $occurrence);
        $movement = JournalEntry::query()->where('user_id', $request->user()->id)->find($request->integer('journal_entry_id'));

        if (! $movement) {
            return back()->with('error', 'Movimento non trovato.');
        }

        $action->link($request->user(), $occurrence, $movement);

        return back()->with('success', 'Movimento collegato alla scadenza. Non verrà contato due volte.');
    }

    public function skip(Request $request, RecurringOccurrence $occurrence, SettleOccurrence $action): RedirectResponse
    {
        $this->owned($request, $occurrence);
        $action->skip($request->user(), $occurrence);

        return back()->with('success', 'Scadenza saltata. Resta nello storico come non contabilizzata.');
    }

    public function update(Request $request, RecurringOccurrence $occurrence, SettleOccurrence $action): RedirectResponse
    {
        $this->owned($request, $occurrence);
        $action->changeAmount($request->user(), $occurrence, $request->string('amount')->toString());

        return back()->with('success', 'Importo della singola scadenza aggiornato.');
    }
}
