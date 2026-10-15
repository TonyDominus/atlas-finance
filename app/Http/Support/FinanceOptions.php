<?php

namespace App\Http\Support;

use App\Enums\AccountStatus;
use App\Models\Account;
use App\Models\Category;
use App\Models\Counterparty;
use App\Models\User;

final class FinanceOptions
{
    /**
     * @return list<array<string, mixed>>
     */
    public function accounts(User $user, bool $activeOnly = false): array
    {
        return Account::query()
            ->where('user_id', $user->id)
            ->when($activeOnly, fn ($query) => $query->where('status', AccountStatus::Active))
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'currency' => $account->currency,
                'type_label' => $account->type->label(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function categories(User $user): array
    {
        return Category::query()
            ->where('user_id', $user->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'kind' => $category->kind->value,
                'parent_id' => $category->parent_id,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function counterparties(User $user): array
    {
        return Counterparty::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Counterparty $counterparty) => [
                'id' => $counterparty->id,
                'name' => $counterparty->name,
            ])
            ->all();
    }
}
