<?php

namespace App\Http\Requests;

use App\Enums\JournalEntryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMovementRequest extends FormRequest
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
            'type' => ['required', Rule::in(array_map(
                fn (JournalEntryType $type) => $type->value,
                array_filter(JournalEntryType::cases(), fn (JournalEntryType $type) => $type->isRecordable()),
            ))],
            'amount' => ['required', 'string', 'max:32'],
            'occurred_on' => ['required', 'date'],
            'booked_on' => ['nullable', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'account_id' => ['required', 'integer'],
            'destination_account_id' => ['nullable', 'integer', 'required_if:type,transfer'],
            'fee_amount' => ['nullable', 'string', 'max:32'],
            'category_id' => ['nullable', 'integer'],
            'counterparty_id' => ['nullable', 'integer'],
            'counterparty_name' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'tags' => ['nullable', 'array', 'max:8'],
            'tags.*' => ['string', 'max:40'],
            'external_id' => ['nullable', 'string', 'max:191'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp,txt,csv'],
            'as_forecast' => ['nullable', 'boolean'],
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

        $this->merge([
            'tags' => $tags ?: [],
            'booked_on' => $this->input('booked_on') ?: $this->input('occurred_on'),
            'external_id' => trim((string) $this->input('external_id', '')) ?: null,
            'as_forecast' => $this->boolean('as_forecast'),
            'counterparty_name' => trim((string) $this->input('counterparty_name', '')) ?: null,
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
            'account_id.required' => 'Seleziona un conto.',
            'occurred_on.required' => 'Indica la data dell\'operazione.',
            'destination_account_id.required_if' => 'Seleziona il conto di destinazione.',
            'attachments.max' => 'Puoi allegare al massimo 5 file.',
            'attachments.*.max' => 'Ogni allegato può pesare al massimo 5 MB.',
        ];
    }
}
