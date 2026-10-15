<?php

namespace App\Http\Requests;

use App\Enums\CounterpartyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCounterpartyRequest extends FormRequest
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
            'type' => ['required', Rule::enum(CounterpartyType::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
