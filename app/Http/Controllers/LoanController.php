<?php

namespace App\Http\Controllers;

use App\Actions\Loans\LoanBook;
use App\Http\Controllers\Concerns\OwnsFinancialRecords;
use App\Http\Requests\RepayLoanRequest;
use App\Http\Requests\StoreLoanRequest;
use App\Http\Support\FinanceOptions;
use App\Models\Loan;
use App\Services\Loans\LoanReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoanController extends Controller
{
    use OwnsFinancialRecords;

    public function index(Request $request, LoanReader $reader): Response
    {
        $loans = Loan::query()
            ->where('user_id', $request->user()->id)
            ->with('counterparty')
            ->orderByDesc('opened_on')
            ->orderByDesc('id')
            ->get()
            ->map(function (Loan $loan) use ($reader) {
                $detail = $reader->detail($loan);

                return [
                    'id' => $detail['id'],
                    'direction' => $detail['direction'],
                    'direction_label' => $detail['direction_label'],
                    'description' => $detail['description'],
                    'counterparty' => $detail['counterparty'],
                    'opened_on' => $detail['opened_on'],
                    'due_on' => $detail['due_on'],
                    'disbursed' => $detail['disbursed'],
                    'residual' => $detail['residual'],
                    'residual_minor' => $detail['residual_minor'],
                    'status' => $detail['status'],
                    'status_label' => $detail['status_label'],
                    'overdue' => $detail['overdue'],
                ];
            });

        return Inertia::render('Loans/Index', [
            'summary' => $reader->summary($request->user()),
            'loans' => $loans,
        ]);
    }

    public function create(Request $request, FinanceOptions $options): Response
    {
        return Inertia::render('Loans/Form', [
            'accounts' => $options->accounts($request->user(), true),
            'counterparties' => $options->counterparties($request->user()),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(StoreLoanRequest $request, LoanBook $book): RedirectResponse
    {
        $loan = $book->open($request->user(), $request->validated());

        return redirect()
            ->route('loans.show', $loan)
            ->with('success', 'Prestito registrato. La liquidità è diminuita, il patrimonio no: è un credito, non una spesa.');
    }

    public function show(Request $request, Loan $loan, LoanReader $reader, FinanceOptions $options): Response
    {
        $this->owned($request, $loan);

        $others = Loan::query()
            ->where('user_id', $request->user()->id)
            ->whereKeyNot($loan->id)
            ->with('counterparty')
            ->get()
            ->map(fn (Loan $other) => [
                'id' => $other->id,
                'label' => $other->counterparty->name.' · '.$other->description,
                'direction' => $other->direction->value,
            ]);

        return Inertia::render('Loans/Show', [
            'loan' => $reader->detail($loan),
            'accounts' => $options->accounts($request->user(), true),
            'others' => $others,
            'today' => now()->toDateString(),
        ]);
    }

    public function disburse(Request $request, Loan $loan, LoanBook $book): RedirectResponse
    {
        $this->owned($request, $loan);
        $data = $request->validate([
            'amount' => ['required', 'string', 'max:32'],
            'account_id' => ['required', 'integer'],
            'occurred_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $book->addDisbursement($request->user(), $loan, $data);

        return back()->with('success', 'Erogazione aggiunta allo stesso prestito.');
    }

    public function repay(RepayLoanRequest $request, LoanBook $book): RedirectResponse
    {
        $book->repay($request->user(), $request->validated());

        return back()->with('success', 'Rimborso registrato. Il capitale non è un\'entrata.');
    }
}
