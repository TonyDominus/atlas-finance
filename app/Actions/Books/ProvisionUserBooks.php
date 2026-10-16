<?php

declare(strict_types=1);

namespace App\Actions\Books;

use App\Enums\CategoryKind;
use App\Enums\LedgerAccountType;
use App\Models\Category;
use App\Models\CategoryRule;
use App\Models\LedgerAccount;
use App\Models\User;
use Illuminate\Support\Str;

final class ProvisionUserBooks
{
    /**
     * @var array<string, array{0: LedgerAccountType, 1: string}>
     */
    private const SYSTEMS = [
        'opening_equity' => [LedgerAccountType::Equity, 'Patrimonio iniziale'],
        'income' => [LedgerAccountType::Income, 'Entrate'],
        'expense' => [LedgerAccountType::Expense, 'Spese'],
        'bank_fees' => [LedgerAccountType::Expense, 'Commissioni bancarie'],
        'interest_income' => [LedgerAccountType::Income, 'Interessi attivi'],
        'investments' => [LedgerAccountType::Asset, 'Investimenti'],
        'investment_results' => [LedgerAccountType::Equity, 'Risultati da investimenti'],
        'loans_receivable' => [LedgerAccountType::Asset, 'Crediti per prestiti'],
        'loans_payable' => [LedgerAccountType::Liability, 'Debiti per prestiti'],
        'loan_adjustments' => [LedgerAccountType::Equity, 'Compensazioni prestiti'],
        'reconciliation' => [LedgerAccountType::Equity, 'Rettifiche di riconciliazione'],
    ];

    public function handle(User $user): void
    {
        $this->ensureCurrency($user, 'EUR');
        $this->ensureCategories($user);
        $this->ensureRules($user);
    }

    public function ensureCurrency(User $user, string $currency): void
    {
        $currency = strtoupper($currency);

        foreach (self::SYSTEMS as $key => [$type, $name]) {
            LedgerAccount::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'system_key' => $key,
                    'currency' => $currency,
                ],
                [
                    'code' => $key.'.'.$currency,
                    'name' => $name,
                    'type' => $type,
                    'is_system' => true,
                ],
            );
        }
    }

    public function system(User $user, string $key, string $currency): LedgerAccount
    {
        $this->ensureCurrency($user, $currency);

        return LedgerAccount::query()
            ->where('user_id', $user->id)
            ->where('system_key', $key)
            ->where('currency', strtoupper($currency))
            ->firstOrFail();
    }

    private function ensureCategories(User $user): void
    {
        $sort = 0;

        foreach ($this->catalog() as [$kind, $name, $children]) {
            $parent = $this->category($user, $name, $kind, null, $sort++);

            foreach ($children as $child) {
                $this->category($user, $child, $kind, $parent->id, $sort++);
            }
        }
    }

    private function category(User $user, string $name, CategoryKind $kind, ?int $parentId, int $sort): Category
    {
        return Category::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'slug' => Str::slug($name),
            ],
            [
                'parent_id' => $parentId,
                'name' => $name,
                'kind' => $kind,
                'is_system' => true,
                'sort_order' => $sort,
            ],
        );
    }

    private function ensureRules(User $user): void
    {
        $priority = 10;

        foreach ($this->rules() as [$field, $pattern, $slug]) {
            $category = Category::query()
                ->where('user_id', $user->id)
                ->where('slug', $slug)
                ->first();

            if (! $category) {
                continue;
            }

            CategoryRule::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'field' => $field,
                    'pattern' => $pattern,
                ],
                [
                    'category_id' => $category->id,
                    'operator' => 'contains',
                    'priority' => $priority,
                ],
            );

            $priority += 10;
        }
    }

    /**
     * @return list<array{0: CategoryKind, 1: string, 2: list<string>}>
     */
    private function catalog(): array
    {
        return [
            [CategoryKind::Expense, 'Alimentari', ['Supermercato', 'Mercato']],
            [CategoryKind::Expense, 'Ristoranti', []],
            [CategoryKind::Expense, 'Casa', ['Manutenzione']],
            [CategoryKind::Expense, 'Mutuo', []],
            [CategoryKind::Expense, 'Affitto', []],
            [CategoryKind::Expense, 'Condominio', []],
            [CategoryKind::Expense, 'Utenze', []],
            [CategoryKind::Expense, 'Trasporti', []],
            [CategoryKind::Expense, 'Automobile', []],
            [CategoryKind::Expense, 'Carburante', []],
            [CategoryKind::Expense, 'Salute', []],
            [CategoryKind::Expense, 'Shopping', []],
            [CategoryKind::Expense, 'Intrattenimento', []],
            [CategoryKind::Expense, 'Tecnologia', []],
            [CategoryKind::Expense, 'Abbonamenti', []],
            [CategoryKind::Expense, 'Commissioni bancarie', []],
            [CategoryKind::Expense, 'Imposte', []],
            [CategoryKind::Expense, 'Regali', []],
            [CategoryKind::Income, 'Stipendio', []],
            [CategoryKind::Income, 'Interessi', []],
            [CategoryKind::Income, 'Altre entrate', []],
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function rules(): array
    {
        return [
            ['description', 'netflix', 'abbonamenti'],
            ['description', 'spotify', 'abbonamenti'],
            ['description', 'disney', 'abbonamenti'],
            ['counterparty', 'netflix', 'abbonamenti'],
            ['description', 'esselunga', 'supermercato'],
            ['description', 'conad', 'supermercato'],
            ['description', 'lidl', 'supermercato'],
            ['description', 'enel', 'utenze'],
            ['description', 'eni gas', 'utenze'],
        ];
    }
}
