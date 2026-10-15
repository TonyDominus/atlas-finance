<?php

namespace App\Http\Requests;

use App\Enums\CategoryKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'kind' => ['required', Rule::enum(CategoryKind::class)],
            'parent_id' => ['nullable', 'integer'],
        ];
    }
}
