<?php

declare(strict_types=1);

namespace App\Actions\Accounts;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateAccount
{
    /**
     * @param  array{
     *     name: string,
     *     institution?: string|null,
     *     type: string,
     *     color: string,
     *     icon: string,
     *     status: string,
     *     include_in_net_worth: bool,
     *     notes?: string|null
     * }  $data
     */
    public function handle(User $user, Account $account, array $data): Account
    {
        if ($account->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'name' => 'Conto non disponibile.',
            ]);
        }

        return DB::transaction(function () use ($user, $account, $data) {
            $institutionId = null;
            $institutionName = trim((string) ($data['institution'] ?? ''));

            if ($institutionName !== '') {
                $institutionId = Institution::query()->firstOrCreate(
                    ['user_id' => $user->id, 'name' => $institutionName],
                )->id;
            }

            $account->fill([
                'institution_id' => $institutionId,
                'name' => trim($data['name']),
                'type' => AccountType::from($data['type']),
                'color' => $data['color'],
                'icon' => $data['icon'],
                'status' => AccountStatus::from($data['status']),
                'include_in_net_worth' => $data['include_in_net_worth'],
                'notes' => $data['notes'] ?? null,
            ])->save();

            $account->ledgerAccount?->forceFill(['name' => $account->name])->save();

            return $account->load('institution', 'ledgerAccount');
        });
    }
}
