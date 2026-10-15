<?php

namespace App\Http\Requests;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
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
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['required', Rule::in(['landmark', 'card', 'wallet', 'cash', 'chart', 'piggy'])],
            'status' => ['required', Rule::enum(AccountStatus::class)],
            'include_in_net_worth' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'include_in_net_worth' => filter_var($this->input('include_in_net_worth', true), FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
