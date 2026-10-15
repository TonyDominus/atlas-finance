<?php

namespace App\Http\Controllers;

use App\Domain\Money\Money;
use App\Models\JournalEntry;
use App\Services\Recurrence\OccurrenceProjector;
use App\Services\Recurrence\RecurrenceReader;
use App\Models\RecurringRule;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function __invoke(Request $request, OccurrenceProjector $projector, RecurrenceReader $reader): Response
    {
        RecurringRule::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->each(fn (RecurringRule $rule) => $projector->sync($rule));

        $from = now()->startOfMonth()->toDateString();
        $until = now()->addMonthsNoOverflow(3)->endOfMonth()->toDateString();
        $items = $reader->timeline($request->user(), $from, $until);

        $linkable = JournalEntry::query()
            ->where('user_id', $request->user()->id)
            ->operational()
            ->whereDoesntHave('occurrence')
            ->with('lines')
            ->orderByDesc('booked_on')
            ->limit(25)
            ->get()
            ->map(fn (JournalEntry $entry) => [
                'id' => $entry->id,
                'description' => $entry->description,
                'booked_on' => $entry->booked_on->toDateString(),
                'amount' => Money::ofMinor((int) ($entry->lines->first()->amount_minor ?? 0), $entry->currency)->format(),
            ]);

        return Inertia::render('Calendar/Index', [
            'items' => $items,
            'linkable' => $linkable,
            'from' => $from,
            'until' => $until,
        ]);
    }
}
