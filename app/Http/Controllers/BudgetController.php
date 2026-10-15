<?php

namespace App\Http\Controllers;

use App\Actions\Budget\SaveBudget;
use App\Http\Controllers\Concerns\OwnsFinancialRecords;
use App\Http\Requests\StoreBudgetRequest;
use App\Http\Support\FinanceOptions;
use App\Models\Budget;
use App\Services\Analytics\AnalyticsReader;
use App\Services\Budget\BudgetReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    use OwnsFinancialRecords;

    public function index(Request $request, BudgetReader $reader, FinanceOptions $options, AnalyticsReader $analytics): Response
    {
        $month = now()->format('Y-m');
        $rows = $reader->forMonth($request->user(), $month);
        $models = Budget::query()->where('user_id', $request->user()->id)->with('category')->get()->keyBy('id');

        foreach ($rows as &$row) {
            $row['history'] = $reader->history($request->user(), $models[$row['id']]);
        }

        return Inertia::render('Budgets/Index', [
            'month' => $month,
            'budgets' => $rows,
            'categories' => array_values(array_filter(
                $options->categories($request->user()),
                fn (array $category) => $category['kind'] === 'expense',
            )),
            'history_sufficient' => $reader->historyIsSufficient($request->user()),
            'notes' => $analytics->headlines($request->user()),
        ]);
    }

    public function store(StoreBudgetRequest $request, SaveBudget $action): RedirectResponse
    {
        $action->handle($request->user(), $request->integer('category_id'), $request->string('amount')->toString());

        return back()->with('success', 'Budget mensile salvato.');
    }

    public function destroy(Request $request, Budget $budget): RedirectResponse
    {
        $this->owned($request, $budget);
        $budget->delete();

        return back()->with('success', 'Budget rimosso.');
    }
}
