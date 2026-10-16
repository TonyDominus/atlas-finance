<?php

namespace App\Console\Commands;

use App\Actions\Investments\RecordPrice;
use App\Models\Instrument;
use App\Models\SecurityTrade;
use App\Models\User;
use App\Services\Investments\AlphaVantageQuotes;
use Illuminate\Console\Command;

class FetchQuotesCommand extends Command
{
    protected $signature = 'atlas:fetch-quotes';

    protected $description = 'Scarica le ultime chiusure da Alpha Vantage per i titoli in portafoglio o seguiti';

    public function handle(RecordPrice $prices, AlphaVantageQuotes $quotes): int
    {
        if (! $quotes->configured()) {
            $this->warn('ALPHAVANTAGE_KEY non impostata: nessuna quotazione scaricata.');

            return self::SUCCESS;
        }

        $heldIds = SecurityTrade::query()->distinct()->pluck('instrument_id')->all();

        User::query()->orderBy('id')->each(function (User $user) use ($prices, $heldIds) {
            $instruments = Instrument::query()
                ->where('user_id', $user->id)
                ->where(fn ($query) => $query->where('watch', true)->orWhereIn('id', $heldIds ?: [0]))
                ->orderBy('symbol')
                ->get();

            if ($instruments->isEmpty()) {
                return;
            }

            $result = $prices->refresh($user, $instruments);
            $this->line("Utente {$user->id}: aggiornati ".count($result['updated']).', non disponibili '.count($result['failed']).'.');

            foreach ($result['failed'] as $symbol => $reason) {
                $this->warn("  {$symbol}: {$reason}");
            }
        });

        return self::SUCCESS;
    }
}
