<script setup lang="ts">
import AccountGlyph from '@/Components/AccountGlyph.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { formatDate } from '@/utils/date';
import { AccountCard, CurrencySnapshot, MovementSummary } from '@/types/finance';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    snapshot: {
        by_currency: CurrencySnapshot[];
        accounts: AccountCard[];
    };
    recent: MovementSummary[];
    upcoming: { id: number; due_on: string; description: string; amount: string; account: string; estimate: boolean }[];
    loans: { currencies: { residual: string; debt: string; overdue: number }[] };
    monthly_recurring: string;
    notes: string[];
}>();

function tone(direction: MovementSummary['direction']): string {
    if (direction === 'in') return 'text-teal-800 dark:text-teal-300';
    if (direction === 'out') return 'text-rose-800 dark:text-rose-300';
    return 'text-stone-700 dark:text-stone-200';
}
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs uppercase tracking-[0.16em] text-stone-500">Oggi</p>
                <h1 class="font-display text-2xl text-stone-900 dark:text-stone-50">Quadro finanziario</h1>
            </div>
        </template>

        <EmptyState
            v-if="snapshot.accounts.length === 0"
            title="Nessun conto ancora"
            body="Crea il primo conto con il saldo iniziale. Atlas registra quel saldo come scrittura di apertura, così il totale resta ricostruibile."
        >
            <template #action>
                <Link :href="route('accounts.create')" class="btn-primary">Nuovo conto</Link>
            </template>
        </EmptyState>

        <div v-else class="space-y-6">
            <section v-for="row in snapshot.by_currency" :key="row.currency" class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Link :href="route('wealth.index')" class="panel block p-5 transition hover:border-teal-700/40">
                        <p class="text-xs uppercase tracking-wider text-stone-500">Liquidità</p>
                        <p class="mt-2 font-display text-3xl tabular-nums">{{ row.liquidity }}</p>
                        <p class="mt-2 text-xs text-stone-500">Spendibile subito. È la base della previsione di cassa.</p>
                    </Link>
                    <Link :href="route('wealth.index')" class="panel block p-5 transition hover:border-teal-700/40">
                        <p class="text-xs uppercase tracking-wider text-stone-500">Fermi</p>
                        <p class="mt-2 font-display text-3xl tabular-nums">{{ row.parked }}</p>
                        <p class="mt-2 text-xs text-stone-500">Risparmi e depositi accantonati, fuori dalla cassa quotidiana.</p>
                    </Link>
                    <Link :href="route('investments.index')" class="panel block p-5 transition hover:border-teal-700/40">
                        <p class="text-xs uppercase tracking-wider text-stone-500">Investimenti</p>
                        <p class="mt-2 font-display text-3xl tabular-nums">{{ row.investments_total }}</p>
                        <p class="mt-2 text-xs text-stone-500">Titoli al costo {{ row.invested }} · liquidità sul conto titoli {{ row.investment_cash }}.</p>
                    </Link>
                    <article class="panel p-5">
                        <p class="text-xs uppercase tracking-wider text-stone-500">Patrimonio netto</p>
                        <p class="mt-2 font-display text-3xl tabular-nums">{{ row.net_worth }}</p>
                        <p class="mt-2 text-xs text-stone-500">Le tre sezioni più crediti e meno debiti dei prestiti. Nessun cambio inventato.</p>
                    </article>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <article class="panel p-5">
                        <p class="text-xs uppercase tracking-wider text-stone-500">Entrate del mese</p>
                        <p class="mt-2 text-2xl font-semibold tabular-nums text-teal-800 dark:text-teal-300">{{ row.income_month }}</p>
                    </article>
                    <article class="panel p-5">
                        <p class="text-xs uppercase tracking-wider text-stone-500">Spese del mese</p>
                        <p class="mt-2 text-2xl font-semibold tabular-nums text-rose-800 dark:text-rose-300">{{ row.expense_month }}</p>
                    </article>
                    <article class="panel p-5">
                        <p class="text-xs uppercase tracking-wider text-stone-500">Risparmio netto</p>
                        <p class="mt-2 text-2xl font-semibold tabular-nums" :class="row.savings_month_minor >= 0 ? 'text-teal-800 dark:text-teal-300' : 'text-rose-800 dark:text-rose-300'">
                            {{ row.savings_month }}
                        </p>
                        <p class="mt-2 text-xs text-stone-500">
                            <template v-if="!row.comparison_available">Storico del mese precedente insufficiente per un confronto.</template>
                            <template v-else>
                                <span v-if="row.expense_change_percent !== null">Spese {{ row.expense_change_percent > 0 ? '+' : '' }}{{ row.expense_change_percent }}% rispetto al mese scorso.</span>
                                <span v-else>Nel mese scorso non risultano spese confrontabili.</span>
                            </template>
                        </p>
                    </article>
                </div>
            </section>

            <section class="grid gap-4 lg:grid-cols-3">
                <article class="panel p-5">
                    <p class="text-xs uppercase tracking-wider text-stone-500">Uscite ricorrenti equivalenti</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums">{{ monthly_recurring }}</p>
                    <p class="mt-2 text-xs text-stone-500">Stima mensile di spese e canoni attivi. Non sono ancora movimenti.</p>
                </article>
                <article class="panel p-5">
                    <div class="flex items-center justify-between">
                        <p class="text-xs uppercase tracking-wider text-stone-500">Prossime scadenze</p>
                        <Link :href="route('calendar.index')" class="text-sm text-teal-800 dark:text-teal-300">Calendario</Link>
                    </div>
                    <p v-if="upcoming.length === 0" class="mt-3 text-sm text-stone-500">Nessuna previsione nei prossimi 45 giorni.</p>
                    <ul v-else class="mt-3 space-y-2 text-sm">
                        <li v-for="item in upcoming" :key="item.id" class="flex justify-between gap-3">
                            <span>{{ formatDate(item.due_on) }} · {{ item.description }}</span>
                            <span class="tabular-nums">{{ item.amount }}</span>
                        </li>
                    </ul>
                </article>
                <article class="panel p-5">
                    <div class="flex items-center justify-between">
                        <p class="text-xs uppercase tracking-wider text-stone-500">Prestiti da recuperare</p>
                        <Link :href="route('loans.index')" class="text-sm text-teal-800 dark:text-teal-300">Apri</Link>
                    </div>
                    <p v-if="loans.currencies.length === 0" class="mt-3 text-sm text-stone-500">Nessun prestito aperto.</p>
                    <ul v-else class="mt-3 space-y-2 text-sm">
                        <li v-for="row in loans.currencies" :key="row.residual">
                            Crediti {{ row.residual }} · debiti {{ row.debt }}
                            <span v-if="row.overdue"> · {{ row.overdue }} scaduti</span>
                        </li>
                    </ul>
                </article>
            </section>

            <section v-if="notes.length" class="panel p-5">
                <h2 class="font-display text-xl">Da tenere d'occhio</h2>
                <ul class="mt-3 space-y-2 text-sm text-stone-600 dark:text-stone-300">
                    <li v-for="note in notes" :key="note">{{ note }}</li>
                </ul>
            </section>

            <section class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
                <div class="panel p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="font-display text-xl">Conti</h2>
                        <Link :href="route('accounts.index')" class="text-sm font-medium text-teal-800 dark:text-teal-300">Vedi tutti</Link>
                    </div>
                    <ul class="divide-y divide-stone-100 dark:divide-white/10">
                        <li v-for="account in snapshot.accounts" :key="account.id">
                            <Link :href="route('accounts.show', account.id)" class="flex items-center gap-3 py-3">
                                <AccountGlyph :name="account.icon" :color="account.color" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium">{{ account.name }}</p>
                                    <p class="text-xs text-stone-500">
                                        {{ account.type_label }} · {{ account.bucket_label }}
                                        <span v-if="!account.include_in_net_worth"> · escluso dal patrimonio</span>
                                    </p>
                                </div>
                                <p class="tabular-nums font-medium">{{ account.balance }}</p>
                            </Link>
                        </li>
                    </ul>
                </div>

                <div class="panel p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="font-display text-xl">Ultimi movimenti</h2>
                        <Link :href="route('movements.create')" class="btn-primary">Nuovo</Link>
                    </div>
                    <EmptyState
                        v-if="recent.length === 0"
                        title="Nessun movimento"
                        body="Le spese, le entrate e i trasferimenti compariranno qui dopo la registrazione."
                    />
                    <ul v-else class="divide-y divide-stone-100 dark:divide-white/10">
                        <li v-for="movement in recent" :key="movement.id">
                            <Link :href="route('movements.show', movement.id)" class="flex items-start justify-between gap-3 py-3">
                                <div>
                                    <p class="font-medium" :class="{ 'line-through opacity-60': movement.voided }">{{ movement.description }}</p>
                                    <p class="text-xs text-stone-500">
                                        {{ movement.type_label }} · {{ formatDate(movement.booked_on) }}
                                        <span v-if="movement.category"> · {{ movement.category }}</span>
                                    </p>
                                </div>
                                <p class="tabular-nums text-sm font-semibold" :class="tone(movement.direction)">
                                    {{ movement.direction === 'out' ? '−' : movement.direction === 'in' ? '+' : '' }}{{ movement.amount }}
                                </p>
                            </Link>
                        </li>
                    </ul>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
