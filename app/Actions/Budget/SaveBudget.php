<?php

declare(strict_types=1);

namespace App\Actions\Budget;

use App\Domain\Money\Money;
use App\Enums\CategoryKind;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class SaveBudget
{
    public function handle(User $user, int $categoryId, string $amount): Budget
    {
        $category = Category::query()->where('user_id', $user->id)->find($categoryId);

        if (! $category || $category->kind !== CategoryKind::Expense) {
            throw ValidationException::withMessages(['category_id' => 'Il budget si applica a una categoria di spesa.']);
        }

        try {
            $money = Money::parse($amount, 'EUR');
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
        }

        if (! $money->isPositive()) {
            throw ValidationException::withMessages(['amount' => 'L\'importo deve essere maggiore di zero.']);
        }

        return Budget::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'category_id' => $category->id,
                'currency' => 'EUR',
            ],
            ['amount_minor' => $money->amountMinor],
        );
    }
}
