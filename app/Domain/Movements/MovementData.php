<?php

declare(strict_types=1);

namespace App\Domain\Movements;

use App\Domain\Money\Money;
use App\Enums\JournalEntryStatus;
use App\Enums\JournalEntryType;
use App\Enums\JournalOrigin;

final readonly class MovementData
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public JournalEntryType $type,
        public Money $amount,
        public string $occurredOn,
        public string $bookedOn,
        public string $description,
        public int $accountId,
        public ?int $destinationAccountId,
        public ?Money $fee,
        public ?int $categoryId,
        public ?int $counterpartyId,
        public ?string $counterpartyName,
        public ?string $notes,
        public array $tags,
        public ?string $externalId,
        public JournalOrigin $origin,
        public JournalEntryStatus $status = JournalEntryStatus::Posted,
    ) {}
}
