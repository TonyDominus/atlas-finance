<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Models\Loan;
use App\Models\LoanAllocation;
use App\Models\LoanDisbursement;

final class LoanPosition
{
    public function disbursed(Loan $loan): int
    {
        return (int) LoanDisbursement::query()->where('loan_id', $loan->id)->sum('amount_minor');
    }

    public function principalPaid(Loan $loan): int
    {
        return (int) LoanAllocation::query()->where('loan_id', $loan->id)->sum('principal_minor');
    }

    public function interestPaid(Loan $loan): int
    {
        return (int) LoanAllocation::query()->where('loan_id', $loan->id)->sum('interest_minor');
    }

    public function residual(Loan $loan): int
    {
        return $this->disbursed($loan) - $this->principalPaid($loan);
    }

    public function status(Loan $loan): string
    {
        $disbursed = $this->disbursed($loan);
        $paid = $this->principalPaid($loan);

        if ($disbursed <= 0 || $paid <= 0) {
            return 'open';
        }

        if ($paid < $disbursed) {
            return 'partial';
        }

        return 'closed';
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'partial' => 'Parzialmente rimborsato',
            'closed' => 'Chiuso',
            default => 'Aperto',
        };
    }
}
