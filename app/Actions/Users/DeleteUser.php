<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DeleteUser
{
    /**
     * Ledger lines and loan records restrict deletion of what they reference,
     * so they go first, inside one transaction, before the user cascade.
     */
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            DB::table('loan_allocations')
                ->whereIn('loan_repayment_id', DB::table('loan_repayments')->select('id')->where('user_id', $user->id))
                ->delete();
            DB::table('loan_repayments')->where('user_id', $user->id)->delete();
            DB::table('loan_disbursements')->where('user_id', $user->id)->delete();
            DB::table('loans')->where('user_id', $user->id)->delete();
            DB::table('journal_entries')->where('user_id', $user->id)->delete();

            $user->delete();
        });
    }
}
