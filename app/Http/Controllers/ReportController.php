<?php

namespace App\Http\Controllers;

use App\Enums\JournalEntryType;
use App\Http\Support\FinanceOptions;
use App\Services\Analytics\AnalyticsReader;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __invoke(Request $request, AnalyticsReader $analytics, FinanceOptions $options): Response
    {
        $filters = [
            'from' => (string) $request->string('from'),
            'to' => (string) $request->string('to'),
            'account_id' => (string) $request->string('account_id'),
            'category_id' => (string) $request->string('category_id'),
            'counterparty_id' => (string) $request->string('counterparty_id'),
            'type' => (string) $request->string('type'),
            'status' => (string) $request->string('status'),
        ];

        return Inertia::render('Reports/Index', [
            'report' => $analytics->report($request->user(), $filters),
            'accounts' => $options->accounts($request->user()),
            'categories' => $options->categories($request->user()),
            'counterparties' => $options->counterparties($request->user()),
            'types' => array_map(
                fn (JournalEntryType $type) => ['value' => $type->value, 'label' => $type->label()],
                [
                    JournalEntryType::Expense,
                    JournalEntryType::Income,
                    JournalEntryType::Fee,
                    JournalEntryType::Refund,
                    JournalEntryType::LoanRepayment,
                ],
            ),
        ]);
    }
}
