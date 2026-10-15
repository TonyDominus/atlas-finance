<?php

namespace App\Http\Controllers;

use App\Actions\Recurrence\ChangeRecurrenceStatus;
use App\Actions\Recurrence\SaveRecurringRule;
use App\Enums\RecurrenceFrequency;
use App\Enums\RecurrenceKind;
use App\Enums\RecurrenceStatus;
use App\Enums\RecurrencePurpose;
use App\Http\Controllers\Concerns\OwnsFinancialRecords;
use App\Http\Requests\SaveRecurringRuleRequest;
use App\Http\Support\FinanceOptions;
use App\Models\RecurringRule;
use App\Services\Recurrence\OccurrenceProjector;
use App\Services\Recurrence\RecurrenceReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecurrenceController extends Controller
{
    use OwnsFinancialRecords;

    public function index(Request $request, RecurrenceReader $reader, OccurrenceProjector $projector): Response
    {
        RecurringRule::query()
            ->where('user_id', $request->user()->id)
            ->where('purpose', 'general')
            ->where('status', 'active')
            ->each(fn (RecurringRule $rule) => $projector->sync($rule));

        $rules = RecurringRule::query()
            ->where('user_id', $request->user()->id)
            ->where('purpose', 'general')
            ->with(['account', 'destinationAccount', 'category', 'counterparty'])
            ->orderBy('description')
            ->get()
            ->map(fn (RecurringRule $rule) => $reader->rule($rule));

        return Inertia::render('Recurrences/Index', [
            'rules' => $rules,
            'monthly' => $reader->monthlyOutflowMinor($request->user()),
        ]);
    }

    public function create(Request $request, FinanceOptions $options): Response
    {
        return Inertia::render('Recurrences/Form', [
            'rule' => null,
            'accounts' => $options->accounts($request->user(), true),
            'categories' => $options->categories($request->user()),
            'counterparties' => $options->counterparties($request->user()),
            'kinds' => RecurrenceKind::options(),
            'frequencies' => RecurrenceFrequency::options(),
            'today' => now()->toDateString(),
            'purpose' => 'general',
        ]);
    }

    public function store(SaveRecurringRuleRequest $request, SaveRecurringRule $action): RedirectResponse
    {
        $rule = $action->create($request->user(), $request->validated());

        if ($rule->purpose === RecurrencePurpose::AccountFee) {
            return redirect()
                ->route('accounts.show', $rule->account_id)
                ->with('success', 'Canone salvato come previsione. Non viene addebitato da solo alla scadenza.');
        }

        return redirect()
            ->route('recurrences.index')
            ->with('success', 'Ricorrenza salvata. Le scadenze restano previsioni finché non le contabilizzi.');
    }

    public function edit(Request $request, RecurringRule $recurrence, FinanceOptions $options): Response
    {
        $this->owned($request, $recurrence);

        return Inertia::render('Recurrences/Form', [
            'rule' => [
                'id' => $recurrence->id,
                'description' => $recurrence->description,
                'kind' => $recurrence->kind->value,
                'amount' => number_format($recurrence->amount_minor / 100, 2, ',', '.'),
                'amount_is_estimate' => $recurrence->amount_is_estimate,
                'account_id' => $recurrence->account_id,
                'destination_account_id' => $recurrence->destination_account_id,
                'category_id' => $recurrence->category_id,
                'counterparty_id' => $recurrence->counterparty_id,
                'frequency' => $recurrence->frequency->value,
                'interval' => $recurrence->interval,
                'interval_unit' => $recurrence->interval_unit ?? 'month',
                'starts_on' => $recurrence->starts_on->toDateString(),
                'ends_on' => $recurrence->ends_on?->toDateString(),
                'max_occurrences' => $recurrence->max_occurrences,
                'reminder_days' => $recurrence->reminder_days,
                'notes' => $recurrence->notes,
            ],
            'accounts' => $options->accounts($request->user(), true),
            'categories' => $options->categories($request->user()),
            'counterparties' => $options->counterparties($request->user()),
            'kinds' => RecurrenceKind::options(),
            'frequencies' => RecurrenceFrequency::options(),
            'today' => now()->toDateString(),
            'purpose' => $recurrence->purpose->value,
        ]);
    }

    public function update(SaveRecurringRuleRequest $request, RecurringRule $recurrence, SaveRecurringRule $action): RedirectResponse
    {
        $this->owned($request, $recurrence);
        $action->update($request->user(), $recurrence, [
            ...$request->validated(),
            'purpose' => $recurrence->purpose->value,
        ]);

        $route = $recurrence->purpose->value === 'account_fee'
            ? redirect()->route('accounts.show', $recurrence->account_id)
            : redirect()->route('recurrences.index');

        return $route->with('success', 'Ricorrenza aggiornata. Le previsioni future seguono il nuovo importo.');
    }

    public function status(Request $request, RecurringRule $recurrence, ChangeRecurrenceStatus $action): RedirectResponse
    {
        $this->owned($request, $recurrence);
        $status = RecurrenceStatus::from($request->string('status')->toString());
        $action->handle($request->user(), $recurrence, $status);

        return back()->with('success', 'Stato aggiornato. Nessun movimento è stato contabilizzato.');
    }

    public function destroy(Request $request, RecurringRule $recurrence, ChangeRecurrenceStatus $action): RedirectResponse
    {
        $this->owned($request, $recurrence);
        $action->delete($request->user(), $recurrence);

        return back()->with('success', 'Ricorrenza eliminata.');
    }
}
