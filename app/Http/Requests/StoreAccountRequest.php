<?php

namespace App\Http\Requests;

use App\Domain\Money\Currency;
use App\Enums\AccountType;
use App\Enums\WealthBucket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'institution' => ['nullable', 'string', 'max:120'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'wealth_bucket' => ['nullable', Rule::enum(WealthBucket::class)],
            'currency' => ['required', 'string', Rule::in(Currency::codes())],
            'opening_balance' => ['required', 'string', 'max:32'],
            'opening_balance_on' => ['required', 'date'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['required', Rule::in(['landmark', 'card', 'wallet', 'cash', 'chart', 'piggy'])],
            'include_in_net_worth' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => strtoupper((string) $this->input('currency', 'EUR')),
            'include_in_net_worth' => filter_var($this->input('include_in_net_worth', true), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Inserisci il nome del conto.',
            'opening_balance.required' => 'Inserisci il saldo iniziale, anche zero.',
            'opening_balance_on.required' => 'Indica la data del saldo iniziale.',
        ];
    }
}
