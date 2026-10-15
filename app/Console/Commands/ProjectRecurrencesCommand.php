<?php

namespace App\Console\Commands;

use App\Models\RecurringRule;
use App\Services\Recurrence\OccurrenceProjector;
use Illuminate\Console\Command;

class ProjectRecurrencesCommand extends Command
{
    protected $signature = 'atlas:project-recurrences {--months=18 : Orizzonte in mesi}';

    protected $description = 'Materializza le scadenze previste senza contabilizzarle';

    public function handle(OccurrenceProjector $projector): int
    {
        $until = now()->addMonthsNoOverflow((int) $this->option('months'))->toDateString();
        $count = 0;

        RecurringRule::query()->orderBy('id')->each(function (RecurringRule $rule) use ($projector, $until, &$count) {
            $projector->sync($rule, $until);
            $count++;
        });

        $this->info("Ricorrenze aggiornate: {$count}. Nessun movimento è stato contabilizzato.");

        return self::SUCCESS;
    }
}
