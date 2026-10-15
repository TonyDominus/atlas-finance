<?php

namespace App\Http\Controllers;

use App\Enums\CategoryKind;
use App\Http\Requests\StoreCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $categories = Category::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'kind' => $category->kind->value,
                'kind_label' => $category->kind->label(),
                'parent_id' => $category->parent_id,
                'is_system' => $category->is_system,
            ]);

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
            'kinds' => [
                ['value' => CategoryKind::Expense->value, 'label' => CategoryKind::Expense->label()],
                ['value' => CategoryKind::Income->value, 'label' => CategoryKind::Income->label()],
            ],
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $kind = CategoryKind::from($request->string('kind')->toString());
        $parentId = $request->integer('parent_id') ?: null;

        if ($parentId) {
            $parent = Category::query()
                ->where('user_id', $request->user()->id)
                ->find($parentId);

            if (! $parent || $parent->kind !== $kind || $parent->parent_id !== null) {
                throw ValidationException::withMessages([
                    'parent_id' => 'La sottocategoria deve appartenere a una categoria principale dello stesso tipo.',
                ]);
            }
        }

        $slug = Str::slug($request->string('name')->toString());

        if (Category::query()->where('user_id', $request->user()->id)->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'name' => 'Esiste già una categoria con questo nome.',
            ]);
        }

        $sort = (int) Category::query()->where('user_id', $request->user()->id)->max('sort_order') + 1;

        Category::query()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $parentId,
            'name' => trim($request->string('name')->toString()),
            'slug' => $slug,
            'kind' => $kind,
            'is_system' => false,
            'sort_order' => $sort,
        ]);

        return redirect()->route('categories.index')->with('success', 'Categoria aggiunta.');
    }
}
