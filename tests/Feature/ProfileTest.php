<?php

use App\Actions\Loans\LoanBook;
use App\Domain\Money\Money;
use App\Enums\JournalEntryType;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertNull($user->fresh());
});

test('user with ledger history, loans and securities can delete their account', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherAccount = atlasAccount($other, 'Conto di un altro', '100,00');
    $current = atlasAccount($user, 'Intesa', '1.000,00');
    $broker = atlasAccount($user, 'Directa', '2.000,00', 'investment');
    atlasMove($user, $current, JournalEntryType::Expense, '20,00', 'Spesa');
    atlasMove($user, $current, JournalEntryType::Transfer, '100,00', 'Giroconto', $broker->id, Money::parse('1,00', 'EUR'));
    $loans = app(LoanBook::class);
    $loan = $loans->open($user, [
        'direction' => 'given',
        'counterparty_name' => 'Marco',
        'amount' => '200,00',
        'account_id' => $current->id,
        'opened_on' => now()->toDateString(),
        'description' => 'Prestito a Marco',
    ]);
    $loans->repay($user, [
        'occurred_on' => now()->toDateString(),
        'kind' => 'cash',
        'account_id' => $current->id,
        'allocations' => [['loan_id' => $loan->id, 'principal' => '50,00', 'interest' => '0,00']],
    ]);
    atlasTrade($user, atlasInstrument($user), $broker->id, 'buy', '10', '1.000,00', ['fee' => '2,00']);

    $this->actingAs($user)
        ->delete('/profile', ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull()
        ->and(JournalEntry::query()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(Loan::query()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and($otherAccount->fresh())->not->toBeNull()
        ->and(JournalEntry::query()->where('user_id', $other->id)->count())->toBe(1);
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/profile');

    $this->assertNotNull($user->fresh());
});
