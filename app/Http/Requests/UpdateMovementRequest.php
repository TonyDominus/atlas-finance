<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMovementRequest extends FormRequest
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
            'description' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['nullable', 'integer'],
            'counterparty_id' => ['nullable', 'integer'],
            'tags' => ['nullable', 'array', 'max:8'],
            'tags.*' => ['string', 'max:40'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $tags = $this->input('tags', []);

        if (is_string($tags)) {
            $tags = collect(explode(',', $tags))
                ->map(fn ($tag) => trim($tag))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $this->merge(['tags' => $tags ?: []]);
    }
}
