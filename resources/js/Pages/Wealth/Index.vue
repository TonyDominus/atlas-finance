<script setup lang="ts">
import AccountGlyph from '@/Components/AccountGlyph.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { AccountCard, CurrencySnapshot, InvestmentTotals, Position } from '@/types/finance';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    by_currency: CurrencySnapshot[];
    sections: { key: string; label: string; hint: string; accounts: AccountCard[] }[];
    positions: Position[];
    investments: InvestmentTotals[];
}>();

function share(part: number | undefined, row: CurrencySnapshot): number {
    const total = Math.max(0, row.liquidity_minor ?? 0) + Math.max(0, row.parked_minor ?? 0) + Math.max(0, row.investments_total_minor ?? 0);

    return total > 0 ? Math.round((Math.max(0, part ?? 0) * 1000) / total) / 10 : 0;
}

const tones: Record<string, string> = {
    liquid: 'bg-teal-700',
    parked: 'bg-amber-600',
    invested: 'bg-sky-700',
};
</script>

<template>
    <Head title="Patrimonio" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs uppercase tracking-[0.16em] text-stone-500">Dove sono i soldi</p>
                <h1 class="font-display text-2xl">Patrimonio</h1>
            </div>
        </template>

        <div class="space-y-6">
            <section v-for="row in by_currency" :key="row.currency" class="panel p-6">
                <div class="flex flex-wrap items-baseline justify-between gap-3">
                    <h2 class="font-display text-xl">Composizione in {{ row.currency }}</h2>
                    <p class="text-sm text-stone-500">Patrimonio netto <span class="font-semibold tabular-nums text-stone-900 dark:text-stone-100">{{ row.net_worth }}</span></p>
                </div>

                <div class="mt-4 flex h-3 overflow-hidden rounded-full bg-stone-100 dark:bg-white/10">
                    <div :class="tones.liquid" :style="{ width: share(row.liquidity_minor, row) + '%' }" />
                    <div :class="tones.parked" :style="{ width: share(row.parked_minor, row) + '%' }" />
                    <div :class="tones.invested" :style="{ width: share(row.investments_total_minor, row) + '%' }" />
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <p class="flex items-center gap-2 text-xs uppercase tracking-wider text-stone-500"><span class="h-2 w-2 rounded-full" :class="tones.liquid" />Liquidità</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums">{{ row.liquidity }}</p>
                        <p class="text-xs text-stone-500">{{ share(row.liquidity_minor, row).toLocaleString('it-IT') }}%</p>
                    </div>
                    <div>
                        <p class="flex items-center gap-2 text-xs uppercase tracking-wider text-stone-500"><span class="h-2 w-2 rounded-full" :class="tones.parked" />Fermi</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums">{{ row.parked }}</p>
                        <p class="text-xs text-stone-500">{{ share(row.parked_minor, row).toLocaleString('it-IT') }}%</p>
                    </div>
                    <div>
                        <p class="flex items-center gap-2 text-xs uppercase tracking-wider text-stone-500"><span class="h-2 w-2 rounded-full" :class="tones.invested" />Investimenti</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums">{{ row.investments_total }}</p>
                        <p class="text-xs text-stone-500">{{ share(row.investments_total_minor, row).toLocaleString('it-IT') }}% · titoli al costo {{ row.invested }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-stone-500">Prestiti, netto</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums">{{ row.loans_net }}</p>
                        <p class="text-xs text-stone-500">Crediti meno debiti verso persone.</p>
                    </div>
                </div>
            </section>

            <div class="grid gap-6 xl:grid-cols-3">
                <section v-for="section in sections" :key="section.key" class="panel p-5">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full" :class="tones[section.key]" />
                        <h2 class="font-display text-xl">{{ section.label }}</h2>
                    </div>
                    <p class="mt-1 text-xs text-stone-500">{{ section.hint }}</p>
                    <p v-if="section.accounts.length === 0" class="mt-4 text-sm text-stone-500">
                        Nessun conto in questa sezione. La sezione si sceglie nella modifica del conto.
                    </p>
                    <ul v-else class="mt-3 divide-y divide-stone-100 dark:divide-white/10">
                        <li v-for="account in section.accounts" :key="account.id">
                            <Link :href="route('accounts.show', account.id)" class="flex items-center gap-3 py-3">
                                <AccountGlyph :name="account.icon" :color="account.color" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium">{{ account.name }}</p>
                                    <p class="text-xs text-stone-500">
                                        {{ account.type_label }}
                                        <span v-if="!account.include_in_net_worth"> · escluso dal patrimonio</span>
                                    </p>
                                </div>
                                <p class="tabular-nums font-medium">{{ account.balance }}</p>
                            </Link>
                        </li>
                    </ul>
                </section>
            </div>

            <section class="panel p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-display text-xl">Titoli in portafoglio</h2>
                        <p class="text-xs text-stone-500">Quantità e costo medio ricostruiti dalle operazioni registrate.</p>
                    </div>
                    <Link :href="route('investments.index')" class="btn-secondary">Operazioni</Link>
                </div>

                <EmptyState
                    v-if="positions.length === 0"
                    class="mt-4"
                    title="Nessun titolo registrato"
                    body="Aggiungi il titolo in Mercato, poi registra l'acquisto in Investimenti con quantità e importo pagato."
                />

                <div v-else class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wider text-stone-500">
                            <tr>
                                <th class="py-2 pr-4">Titolo</th>
                                <th class="py-2 pr-4">Conto</th>
                                <th class="py-2 pr-4 text-right">Quantità</th>
                                <th class="py-2 pr-4 text-right">Costo</th>
                                <th class="py-2 text-right">Valore</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100 dark:divide-white/10">
                            <tr v-for="position in positions" :key="`${position.instrument_id}-${position.account_id}`">
                                <td class="py-2 pr-4"><span class="font-semibold">{{ position.symbol }}</span> <span class="text-stone-500">{{ position.name }}</span></td>
                                <td class="py-2 pr-4">{{ position.account }}</td>
                                <td class="py-2 pr-4 text-right tabular-nums">{{ position.quantity }}</td>
                                <td class="py-2 pr-4 text-right tabular-nums">{{ position.cost }}</td>
                                <td class="py-2 text-right tabular-nums">
                                    <template v-if="position.value">{{ position.value }}</template>
                                    <span v-else class="text-stone-400">senza prezzo</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-for="row in investments.filter((item) => item.unassigned_minor !== 0)" :key="row.currency" class="mt-3 text-xs text-stone-500">
                    {{ row.unassigned }} di investimenti registrati senza titolo (movimenti generici di acquisto o vendita). Restano nel patrimonio, al costo.
                </p>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
