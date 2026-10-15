<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RepayLoanRequest extends FormRequest
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
            'occurred_on' => ['required', 'date'],
            'kind' => ['required', Rule::in(['cash', 'compensation'])],
            'account_id' => ['nullable', 'integer', 'required_if:kind,cash'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'surplus_as' => ['nullable', Rule::in(['interest'])],
            'allocations' => ['required', 'array', 'min:1', 'max:12'],
            'allocations.*.loan_id' => ['required', 'integer'],
            'allocations.*.principal' => ['nullable', 'string', 'max:32'],
            'allocations.*.interest' => ['nullable', 'string', 'max:32'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $allocations = collect($this->input('allocations', []))
            ->filter(fn ($row) => is_array($row) && (int) ($row['loan_id'] ?? 0) > 0)
            ->values()
            ->all();

        $this->merge([
            'allocations' => $allocations,
            'surplus_as' => $this->input('surplus_as') ?: null,
            'account_id' => $this->input('account_id') ?: null,
            'notes' => $this->input('notes') ?: null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_id.required_if' => 'Seleziona il conto del movimento di denaro.',
            'allocations.required' => 'Indica almeno un prestito.',
        ];
    }
}
