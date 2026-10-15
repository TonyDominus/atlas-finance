<?php

namespace App\Enums;

enum JournalEntryType: string
{
    case OpeningBalance = 'opening_balance';
    case Expense = 'expense';
    case Income = 'income';
    case Transfer = 'transfer';
    case Fee = 'fee';
    case Interest = 'interest';
    case Reconciliation = 'reconciliation';
    case Refund = 'refund';
    case Reversal = 'reversal';
    case InvestmentBuy = 'investment_buy';
    case InvestmentSell = 'investment_sell';
    case LoanDisbursement = 'loan_disbursement';
    case LoanRepayment = 'loan_repayment';

    public function label(): string
    {
        return match ($this) {
            self::OpeningBalance => 'Saldo iniziale',
            self::Expense => 'Spesa',
            self::Income => 'Entrata',
            self::Transfer => 'Trasferimento',
            self::Fee => 'Commissione',
            self::Interest => 'Interessi',
            self::Reconciliation => 'Riconciliazione',
            self::Refund => 'Rimborso',
            self::Reversal => 'Storno',
            self::InvestmentBuy => 'Acquisto investimento',
            self::InvestmentSell => 'Vendita investimento',
            self::LoanDisbursement => 'Erogazione prestito',
            self::LoanRepayment => 'Rimborso prestito',
        };
    }

    public function isRecordable(): bool
    {
        return in_array($this, [
            self::Expense,
            self::Income,
            self::Transfer,
            self::Fee,
            self::Refund,
            self::InvestmentBuy,
            self::InvestmentSell,
        ], true);
    }

    /**
     * @return list<array{value: string, label: string, hint: string}>
     */
    public static function recordableOptions(): array
    {
        return [
            ['value' => self::Expense->value, 'label' => 'Spesa', 'hint' => 'Riduce il conto e conta come uscita.'],
            ['value' => self::Income->value, 'label' => 'Entrata', 'hint' => 'Aumenta il conto e conta come entrata.'],
            ['value' => self::Transfer->value, 'label' => 'Trasferimento', 'hint' => 'Sposta denaro tra i tuoi conti. Non è una spesa.'],
            ['value' => self::Fee->value, 'label' => 'Commissione', 'hint' => 'Canone o costo bancario, separato dal trasferimento.'],
            ['value' => self::Refund->value, 'label' => 'Rimborso', 'hint' => 'Storna una spesa. Non è un ricavo.'],
            ['value' => self::InvestmentBuy->value, 'label' => 'Acquisto investimento', 'hint' => 'Converte liquidità in strumenti. Il patrimonio non cambia.'],
            ['value' => self::InvestmentSell->value, 'label' => 'Vendita investimento', 'hint' => 'Riporta uno strumento in liquidità. Non è un ricavo.'],
        ];
    }
}
