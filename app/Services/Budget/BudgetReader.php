<?php

declare(strict_types=1);

namespace App\Services\Budget;

use App\Domain\Money\Money;
use App\Enums\JournalEntryType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\JournalEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class BudgetReader
{
    /**
     * @return list<array<string, mixed>>
     */
    public function forMonth(User $user, string $month): array
    {
        $start = CarbonImmutable::parse($month.'-01')->startOfMonth();
        $end = $start->endOfMonth();
        $budgets = Budget::query()
            ->where('user_id', $user->id)
            ->with('category')
            ->orderBy('id')
            ->get();

        return $budgets->map(function (Budget $budget) use ($user, $start, $end) {
            $used = $this->used($user, $budget->category, $budget->currency, $start->toDateString(), $end->toDateString());
            $remaining = $budget->amount_minor - $used;
            $percent = $budget->amount_minor > 0 ? (int) round(($used * 100) / $budget->amount_minor) : null;

            return [
                'id' => $budget->id,
                'category_id' => $budget->category_id,
                'category' => $budget->category->name,
                'currency' => $budget->currency,
                'amount_minor' => $budget->amount_minor,
                'amount' => Money::ofMinor($budget->amount_minor, $budget->currency)->format(),
                'used_minor' => $used,
                'used' => Money::ofMinor($used, $budget->currency)->format(),
                'remaining_minor' => $remaining,
                'remaining' => Money::ofMinor($remaining, $budget->currency)->format(),
                'percent' => $percent,
                'over' => $remaining < 0,
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(User $user, Budget $budget, int $months = 6): array
    {
        $rows = [];
        $cursor = CarbonImmutable::now()->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $start = $cursor->subMonthsNoOverflow($i);
            $used = $this->used($user, $budget->category, $budget->currency, $start->toDateString(), $start->endOfMonth()->toDateString());
            $rows[] = [
                'month' => $start->format('Y-m'),
                'label' => $start->locale('it')->isoFormat('MMM YYYY'),
                'used_minor' => $used,
                'used' => Money::ofMinor($used, $budget->currency)->format(),
                'amount_minor' => $budget->amount_minor,
                'over' => $used > $budget->amount_minor,
            ];
        }

        return array_reverse($rows);
    }

    public function used(User $user, Category $category, string $currency, string $from, string $to): int
    {
        $ids = Category::query()
            ->where('user_id', $user->id)
            ->where(function ($query) use ($category) {
                $query->whereKey($category->id)->orWhere('parent_id', $category->id);
            })
            ->pluck('id');

        $entries = JournalEntry::query()
            ->where('user_id', $user->id)
            ->operational()
            ->where('currency', $currency)
            ->whereIn('category_id', $ids)
            ->whereIn('type', [
                JournalEntryType::Expense->value,
                JournalEntryType::Fee->value,
                JournalEntryType::Refund->value,
            ])
            ->whereBetween('booked_on', [$from, $to])
            ->with('lines')
            ->get();

        $total = 0;

        foreach ($entries as $entry) {
            $amount = (int) ($entry->lines->first()->amount_minor ?? 0);
            $total += $entry->type === JournalEntryType::Refund ? -$amount : $amount;
        }

        return $total;
    }

    public function historyIsSufficient(User $user): bool
    {
        $cursor = CarbonImmutable::now()->startOfMonth();

        for ($i = 1; $i <= 3; $i++) {
            $start = $cursor->subMonthsNoOverflow($i);
            $exists = JournalEntry::query()
                ->where('user_id', $user->id)
                ->operational()
                ->whereBetween('booked_on', [$start->toDateString(), $start->endOfMonth()->toDateString()])
                ->exists();

            if (! $exists) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  Collection<int, Budget>  $budgets
     * @return list<string>
     */
    public function comparisons(User $user, Collection $budgets): array
    {
        if (! $this->historyIsSufficient($user)) {
            return [];
        }

        $messages = [];
        $currentStart = CarbonImmutable::now()->startOfMonth();
        $currentEnd = $currentStart->endOfMonth()->toDateString();

        foreach ($budgets as $budget) {
            $current = $this->used($user, $budget->category, $budget->currency, $currentStart->toDateString(), $currentEnd);
            $previous = [];

            for ($i = 1; $i <= 3; $i++) {
                $start = $currentStart->subMonthsNoOverflow($i);
                $previous[] = $this->used($user, $budget->category, $budget->currency, $start->toDateString(), $start->endOfMonth()->toDateString());
            }

            $average = intdiv(array_sum($previous), 3);

            if ($average <= 0) {
                if ($current > 0) {
                    $messages[] = 'Per '.$budget->category->name.' non c\'è una media utile: nei tre mesi precedenti non risultano spese in questa categoria.';
                }

                continue;
            }

            $percent = (int) round((($current - $average) * 100) / $average);

            if ($percent === 0) {
                continue;
            }

            $direction = $percent > 0 ? 'in più' : 'in meno';
            $messages[] = 'Questo mese hai speso il '.abs($percent).'% '.$direction.' in '.mb_strtolower($budget->category->name).' rispetto alla media degli ultimi tre mesi.';
        }

        return $messages;
    }
}
