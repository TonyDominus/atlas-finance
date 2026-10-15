<?php

namespace Database\Seeders;

use App\Actions\Accounts\CreateAccount;
use App\Actions\Movements\RecordMovement;
use App\Domain\Money\Money;
use App\Domain\Movements\MovementData;
use App\Enums\JournalEntryType;
use App\Enums\JournalOrigin;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'demo@atlas.local'],
            [
                'name' => 'Demo Atlas',
                'password' => 'password',
            ],
        );

        if ($user->accounts()->exists()) {
            return;
        }

        $create = app(CreateAccount::class);
        $record = app(RecordMovement::class);
        $today = now()->toDateString();

        $intesa = $create->handle($user, $this->account('Intesa Sanpaolo', 'Intesa Sanpaolo', 'checking', '2500,00', '#1d4ed8', 'landmark'));
        $hype = $create->handle($user, $this->account('HYPE', 'HYPE', 'card', '120,00', '#7c3aed', 'card'));
        $create->handle($user, $this->account('Contanti', null, 'cash', '80,00', '#0f766e', 'cash'));
        $paypal = $create->handle($user, $this->account('PayPal', 'PayPal', 'wallet', '45,00', '#0369a1', 'wallet'));

        $record->handle($user, new MovementData(
            type: JournalEntryType::Expense,
            amount: Money::parse('12,50', 'EUR'),
            occurredOn: $today,
            bookedOn: $today,
            description: 'Caffè al bar',
            accountId: $hype->id,
            destinationAccountId: null,
            fee: null,
            categoryId: null,
            counterpartyId: null,
            counterpartyName: 'Bar',
            notes: null,
            tags: [],
            externalId: null,
            origin: JournalOrigin::Manual,
        ));

        $record->handle($user, new MovementData(
            type: JournalEntryType::Transfer,
            amount: Money::parse('300,00', 'EUR'),
            occurredOn: $today,
            bookedOn: $today,
            description: 'Ricarica HYPE',
            accountId: $intesa->id,
            destinationAccountId: $hype->id,
            fee: null,
            categoryId: null,
            counterpartyId: null,
            counterpartyName: null,
            notes: null,
            tags: [],
            externalId: null,
            origin: JournalOrigin::Manual,
        ));

        $record->handle($user, new MovementData(
            type: JournalEntryType::Income,
            amount: Money::parse('15,00', 'EUR'),
            occurredOn: $today,
            bookedOn: $today,
            description: 'Vendita usata',
            accountId: $paypal->id,
            destinationAccountId: null,
            fee: null,
            categoryId: null,
            counterpartyId: null,
            counterpartyName: null,
            notes: null,
            tags: [],
            externalId: null,
            origin: JournalOrigin::Manual,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function account(string $name, ?string $institution, string $type, string $opening, string $color, string $icon): array
    {
        return [
            'name' => $name,
            'institution' => $institution,
            'type' => $type,
            'currency' => 'EUR',
            'opening_balance' => $opening,
            'opening_balance_on' => now()->startOfMonth()->toDateString(),
            'color' => $color,
            'icon' => $icon,
            'include_in_net_worth' => true,
            'notes' => null,
        ];
    }
}
