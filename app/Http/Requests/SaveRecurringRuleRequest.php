<?php

namespace App\Http\Requests;

use App\Enums\RecurrenceFrequency;
use App\Enums\RecurrenceKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRecurringRuleRequest extends FormRequest
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
            'kind' => ['required', Rule::enum(RecurrenceKind::class)],
            'purpose' => ['nullable', Rule::in(['general', 'account_fee'])],
            'amount' => ['required', 'string', 'max:32'],
            'amount_is_estimate' => ['nullable', 'boolean'],
            'account_id' => ['required', 'integer'],
            'destination_account_id' => ['nullable', 'integer', 'required_if:kind,transfer'],
            'category_id' => ['nullable', 'integer'],
            'counterparty_id' => ['nullable', 'integer'],
            'frequency' => ['required', Rule::enum(RecurrenceFrequency::class)],
            'interval' => ['nullable', 'integer', 'min:1', 'max:36'],
            'interval_unit' => ['nullable', Rule::in(['day', 'week', 'month']), 'required_if:frequency,custom'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'max_occurrences' => ['nullable', 'integer', 'min:1', 'max:500'],
            'reminder_days' => ['nullable', 'integer', 'min:0', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount_is_estimate' => $this->boolean('amount_is_estimate'),
            'purpose' => $this->input('purpose') ?: 'general',
            'interval' => $this->input('interval') ?: 1,
            'ends_on' => $this->input('ends_on') ?: null,
            'max_occurrences' => $this->input('max_occurrences') !== '' ? $this->input('max_occurrences') : null,
            'reminder_days' => $this->input('reminder_days') !== '' && $this->input('reminder_days') !== null
                ? $this->input('reminder_days')
                : null,
            'category_id' => $this->input('category_id') ?: null,
            'counterparty_id' => $this->input('counterparty_id') ?: null,
            'destination_account_id' => $this->input('destination_account_id') ?: null,
            'notes' => $this->input('notes') ?: null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'description.required' => 'Inserisci una descrizione.',
            'amount.required' => 'Inserisci un importo.',
            'account_id.required' => 'Seleziona il conto.',
            'starts_on.required' => 'Indica la data di partenza.',
            'destination_account_id.required_if' => 'Seleziona il conto di destinazione.',
            'interval_unit.required_if' => 'Scegli l\'unità della ricorrenza personalizzata.',
        ];
    }
}
