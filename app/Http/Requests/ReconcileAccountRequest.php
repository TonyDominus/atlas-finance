<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReconcileAccountRequest extends FormRequest
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
            'statement_balance' => ['required', 'string', 'max:32'],
            'reconciled_on' => ['required', 'date'],
            'post_adjustment' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'post_adjustment' => filter_var($this->input('post_adjustment', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
