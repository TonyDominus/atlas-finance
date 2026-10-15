<?php

namespace App\Models;

use App\Enums\RecurrenceFrequency;
use App\Enums\RecurrenceKind;
use App\Enums\RecurrencePurpose;
use App\Enums\RecurrenceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringRule extends Model
{
    protected $fillable = [
        'user_id',
        'account_id',
        'destination_account_id',
        'category_id',
        'counterparty_id',
        'kind',
        'purpose',
        'description',
        'amount_minor',
        'amount_is_estimate',
        'currency',
        'frequency',
        'interval',
        'interval_unit',
        'anchor_day',
        'starts_on',
        'ends_on',
        'max_occurrences',
        'reminder_days',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'kind' => RecurrenceKind::class,
            'purpose' => RecurrencePurpose::class,
            'frequency' => RecurrenceFrequency::class,
            'status' => RecurrenceStatus::class,
            'amount_minor' => 'integer',
            'amount_is_estimate' => 'boolean',
            'interval' => 'integer',
            'anchor_day' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'max_occurrences' => 'integer',
            'reminder_days' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'destination_account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(RecurringOccurrence::class);
    }

    public function monthlyEquivalentMinor(): int
    {
        $interval = max(1, (int) $this->interval);
        $amount = (int) $this->amount_minor;
        $unit = $this->frequency === RecurrenceFrequency::Custom
            ? ($this->interval_unit ?: 'month')
            : null;

        return match (true) {
            $this->frequency === RecurrenceFrequency::Daily, $unit === 'day' => intdiv($amount * 365, 12 * $interval),
            $this->frequency === RecurrenceFrequency::Weekly, $unit === 'week' => intdiv($amount * 52, 12 * $interval),
            $this->frequency === RecurrenceFrequency::Monthly, $unit === 'month' => intdiv($amount, $interval),
            $this->frequency === RecurrenceFrequency::Quarterly => intdiv($amount, 3 * $interval),
            $this->frequency === RecurrenceFrequency::Semiannual => intdiv($amount, 6 * $interval),
            $this->frequency === RecurrenceFrequency::Yearly => intdiv($amount, 12 * $interval),
            default => intdiv($amount, $interval),
        };
    }
}
