<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\CategoryRule;
use App\Models\User;

final class SuggestCategory
{
    public function match(User $user, ?string $description, ?string $counterpartyName): ?Category
    {
        $rules = CategoryRule::query()
            ->where('user_id', $user->id)
            ->with('category')
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            $haystack = match ($rule->field) {
                'description' => $description ?? '',
                'counterparty' => $counterpartyName ?? '',
                default => '',
            };

            if ($haystack === '') {
                continue;
            }

            if (mb_stripos($haystack, $rule->pattern) !== false) {
                return $rule->category;
            }
        }

        return null;
    }
}
