<?php

namespace App\Http\Controllers;

use App\Domain\Money\Money;
use App\Http\Presenters\MovementPresenter;
use App\Models\JournalEntry;
use App\Models\RecurringRule;
use App\Services\Analytics\AnalyticsReader;
use App\Services\Ledger\PortfolioReader;
use App\Services\Loans\LoanReader;
use App\Services\Recurrence\OccurrenceProjector;
use App\Services\Recurrence\RecurrenceReader;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        PortfolioReader $portfolio,
        MovementPresenter $presenter,
        RecurrenceReader $recurrences,
        OccurrenceProjector $projector,
        LoanReader $loans,
        AnalyticsReader $analytics,
    ): Response {
        RecurringRule::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->each(fn (RecurringRule $rule) => $projector->sync($rule, now()->addMonthsNoOverflow(2)->toDateString()));

        $recent = JournalEntry::query()
            ->where('user_id', $request->user()->id)
            ->with(['category', 'counterparty', 'lines.ledgerAccount.financialAccount'])
            ->orderByDesc('booked_on')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $upcoming = array_values(array_filter(
            $recurrences->timeline($request->user(), now()->toDateString(), now()->addDays(45)->toDateString()),
            fn (array $item) => $item['status'] === 'forecast',
        ));

        return Inertia::render('Dashboard', [
            'snapshot' => $portfolio->for($request->user()),
            'recent' => $recent->map(fn (JournalEntry $entry) => $presenter->summary($entry))->all(),
            'upcoming' => array_slice($upcoming, 0, 5),
            'loans' => $loans->summary($request->user()),
            'monthly_recurring_minor' => $recurrences->monthlyOutflowMinor($request->user()),
            'monthly_recurring' => \App\Domain\Money\Money::ofMinor($recurrences->monthlyOutflowMinor($request->user()), 'EUR')->format(),
            'notes' => $analytics->headlines($request->user()),
        ]);
    }
}
