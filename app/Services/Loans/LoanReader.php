<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Domain\Money\Money;
use App\Models\Loan;
use App\Models\LoanAllocation;
use App\Models\LoanDisbursement;
use App\Models\User;
use Carbon\CarbonImmutable;

final class LoanReader
{
    public function __construct(private LoanPosition $position) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(User $user): array
    {
        $loans = Loan::query()->where('user_id', $user->id)->with('counterparty')->get();
        $currencies = [];

        foreach ($loans as $loan) {
            $currency = $loan->currency;
            $status = $this->position->status($loan);
            $residual = $this->position->residual($loan);
            $bucket = $currencies[$currency] ?? [
                'currency' => $currency,
                'given_minor' => 0,
                'recovered_minor' => 0,
                'residual_minor' => 0,
                'debt_minor' => 0,
                'overdue' => 0,
            ];

            if ($loan->direction->value === 'given') {
                $bucket['given_minor'] += $this->position->disbursed($loan);
                $bucket['recovered_minor'] += $this->position->principalPaid($loan);
                $bucket['residual_minor'] += $residual;
            } else {
                $bucket['debt_minor'] += $residual;
            }

            if ($this->overdue($loan, $status)) {
                $bucket['overdue']++;
            }

            $currencies[$currency] = $bucket;
        }

        $rows = [];

        foreach ($currencies as $currency => $bucket) {
            $rows[] = [
                ...$bucket,
                'given' => Money::ofMinor($bucket['given_minor'], $currency)->format(),
                'recovered' => Money::ofMinor($bucket['recovered_minor'], $currency)->format(),
                'residual' => Money::ofMinor($bucket['residual_minor'], $currency)->format(),
                'debt' => Money::ofMinor($bucket['debt_minor'], $currency)->format(),
            ];
        }

        return ['currencies' => $rows];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Loan $loan): array
    {
        $loan->load([
            'counterparty',
            'disbursements.account',
            'allocations.repayment.account',
        ]);

        $status = $this->position->status($loan);
        $currency = $loan->currency;
        $disbursed = $this->position->disbursed($loan);
        $principal = $this->position->principalPaid($loan);
        $interest = $this->position->interestPaid($loan);
        $residual = $disbursed - $principal;

        $repayments = $loan->allocations
            ->sortByDesc(fn (LoanAllocation $allocation) => $allocation->repayment->occurred_on->toDateString().$allocation->id)
            ->values()
            ->map(function (LoanAllocation $allocation) use ($currency) {
                $repayment = $allocation->repayment;

                return [
                    'id' => $repayment->id,
                    'occurred_on' => $repayment->occurred_on->toDateString(),
                    'kind' => $repayment->kind->value,
                    'kind_label' => $repayment->kind->label(),
                    'account' => $repayment->account?->name,
                    'principal' => Money::ofMinor($allocation->principal_minor, $currency)->format(),
                    'interest' => Money::ofMinor($allocation->interest_minor, $currency)->format(),
                    'principal_minor' => $allocation->principal_minor,
                    'interest_minor' => $allocation->interest_minor,
                    'notes' => $repayment->notes,
                    'movement_id' => $repayment->journal_entry_id,
                ];
            })
            ->all();

        return [
            'id' => $loan->id,
            'direction' => $loan->direction->value,
            'direction_label' => $loan->direction->label(),
            'description' => $loan->description,
            'notes' => $loan->notes,
            'counterparty' => $loan->counterparty->name,
            'counterparty_id' => $loan->counterparty_id,
            'currency' => $currency,
            'opened_on' => $loan->opened_on->toDateString(),
            'due_on' => $loan->due_on?->toDateString(),
            'agreed_interest' => $loan->agreed_interest_minor !== null
                ? Money::ofMinor($loan->agreed_interest_minor, $currency)->format()
                : null,
            'disbursed' => Money::ofMinor($disbursed, $currency)->format(),
            'disbursed_minor' => $disbursed,
            'recovered' => Money::ofMinor($principal, $currency)->format(),
            'recovered_minor' => $principal,
            'interest_received' => Money::ofMinor($interest, $currency)->format(),
            'residual' => Money::ofMinor($residual, $currency)->format(),
            'residual_minor' => $residual,
            'status' => $status,
            'status_label' => $this->position->statusLabel($status),
            'overdue' => $this->overdue($loan, $status),
            'disbursements' => $loan->disbursements->sortByDesc('occurred_on')->values()->map(fn (LoanDisbursement $row) => [
                'id' => $row->id,
                'occurred_on' => $row->occurred_on->toDateString(),
                'account' => $row->account->name,
                'amount' => Money::ofMinor($row->amount_minor, $currency)->format(),
                'notes' => $row->notes,
                'movement_id' => $row->journal_entry_id,
            ])->all(),
            'repayments' => $repayments,
        ];
    }

    private function overdue(Loan $loan, string $status): bool
    {
        return $status !== 'closed'
            && $loan->due_on !== null
            && $loan->due_on->toDateString() < CarbonImmutable::now()->toDateString();
    }
}
