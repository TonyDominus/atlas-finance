<?php

namespace App\Console\Commands;

use App\Enums\JournalEntryStatus;
use App\Enums\JournalSide;
use App\Models\JournalEntry;
use Illuminate\Console\Command;

class LedgerCheckCommand extends Command
{
    protected $signature = 'atlas:ledger-check';

    protected $description = 'Verifica che ogni scrittura contabilizzata sia bilanciata';

    public function handle(): int
    {
        $broken = 0;

        JournalEntry::query()
            ->where('status', JournalEntryStatus::Posted)
            ->with('lines')
            ->orderBy('id')
            ->chunkById(200, function ($entries) use (&$broken) {
                foreach ($entries as $entry) {
                    $debit = 0;
                    $credit = 0;

                    if ($entry->lines->count() < 2) {
                        $this->error("Scrittura {$entry->id} con meno di due righe.");
                        $broken++;
                    }

                    foreach ($entry->lines as $line) {
                        if ($line->currency !== $entry->currency) {
                            $this->error("Scrittura {$entry->id} con valuta incoerente.");
                            $broken++;
                        }

                        if ($line->side === JournalSide::Debit) {
                            $debit += $line->amount_minor;
                        } else {
                            $credit += $line->amount_minor;
                        }
                    }

                    if ($debit !== $credit) {
                        $this->error("Scrittura {$entry->id} non bilanciata ({$debit} / {$credit}).");
                        $broken++;
                    }
                }
            });

        if ($broken > 0) {
            $this->error("Integrità contabile fallita: {$broken} anomalie.");

            return self::FAILURE;
        }

        $this->info('Ledger integro.');

        return self::SUCCESS;
    }
}
