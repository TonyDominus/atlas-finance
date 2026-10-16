<?php

namespace App\Http\Requests;

use App\Enums\LoanDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanRequest extends FormRequest
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
            'direction' => ['required', Rule::enum(LoanDirection::class)],
            'counterparty_id' => ['nullable', 'integer', 'required_without:counterparty_name'],
            'counterparty_name' => ['nullable', 'string', 'max:120', 'required_without:counterparty_id'],
            'amount' => ['required', 'string', 'max:32'],
            'account_id' => ['required', 'integer'],
            'opened_on' => ['required', 'date'],
            'due_on' => ['nullable', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'agreed_interest' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'description.required' => 'Inserisci una descrizione.',
            'amount.required' => 'Inserisci il capitale.',
            'account_id.required' => 'Seleziona il conto.',
            'counterparty_name.required_without' => 'Indica la persona.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'counterparty_id' => $this->input('counterparty_id') ?: null,
            'counterparty_name' => trim((string) $this->input('counterparty_name', '')) ?: null,
            'due_on' => $this->input('due_on') ?: null,
            'agreed_interest' => trim((string) $this->input('agreed_interest', '')) ?: null,
            'notes' => $this->input('notes') ?: null,
        ]);
    }
}
