<?php

namespace App\Policies;

use App\Models\Counterparty;
use App\Models\User;

class CounterpartyPolicy
{
    public function view(User $user, Counterparty $counterparty): bool
    {
        return $user->id === $counterparty->user_id;
    }

    public function update(User $user, Counterparty $counterparty): bool
    {
        return $user->id === $counterparty->user_id;
    }

    public function delete(User $user, Counterparty $counterparty): bool
    {
        return $user->id === $counterparty->user_id;
    }
}
