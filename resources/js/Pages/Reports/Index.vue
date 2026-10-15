<script setup lang="ts">
import FinanceChart from '@/Components/FinanceChart.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatDate } from '@/utils/date';
import { AccountOption, CategoryOption, CounterpartyOption, Option } from '@/types/finance';
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps<{
    report: {
        filters: Record<string, string>;
        actuals_note: string;
        income_expense: { labels: string[]; income: number[]; expense: number[] };
        savings: { labels: string[]; values: number[] };
        categories: { labels: string[]; values: number[] };
        liquidity: { labels: string[]; values: number[] };
        net_worth: { labels: string[]; values: number[] };
        accounts: { labels: string[]; values: number[]; colors: string[] };
        bank_fees: { trailing: string; annualized: string; note: string };
        forecast: {
            estimate: boolean;
            from: string;
            until: string;
            note: string;
            currencies: {
                currency: string;
                opening: string;
                closing: string;
                minimum: string;
                minimum_on: string;
                negative_ranges: { from: string; until: string }[];
                accounts: { id: number; name: string; opening: string; closing: string }[];
                points: { date: string; minor: number }[];
                events: { date: string; description: string; amount: string; delta: number; source: string }[];
            }[];
        };
    };
    accounts: AccountOption[];
    categories: CategoryOption[];
    counterparties: CounterpartyOption[];
    types: Option[];
}>();

const filters = reactive({ ...props.report.filters });

const forecast = computed(() => props.report.forecast.currencies[0] ?? null);

function apply(): void {
    router.get(route('reports.index'), { ...filters }, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <Head title="Analisi" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs uppercase tracking-[0.16em] text-stone-500">Dati reali e stime</p>
                <h1 class="font-display text-2xl">Analisi</h1>
            </div>
        </template>

        <form class="panel mb-6 grid gap-3 p-4 md:grid-cols-3 xl:grid-cols-6" @submit.prevent="apply">
            <input v-model="filters.from" type="date" class="field" />
            <input v-model="filters.to" type="date" class="field" />
            <select v-model="filters.account_id" class="field">
                <option value="">Tutti i conti</option>
                <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
            </select>
            <select v-model="filters.category_id" class="field">
                <option value="">Tutte le categorie</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
            </select>
            <select v-model="filters.counterparty_id" class="field">
                <option value="">Tutte le controparti</option>
                <option v-for="person in counterparties" :key="person.id" :value="person.id">{{ person.name }}</option>
            </select>
            <select v-model="filters.type" class="field">
                <option value="">Tutti i tipi di reddito e spesa</option>
                <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
            </select>
            <select v-model="filters.status" class="field">
                <option value="">Contabilizzati</option>
                <option value="posted">Contabilizzati</option>
                <option value="forecast">Solo previsioni</option>
            </select>
            <button class="btn-primary">Filtra</button>
        </form>

        <p class="mb-4 text-sm text-stone-500">{{ report.actuals_note }} Trasferimenti, acquisti di investimenti e capitale dei prestiti non entrano tra le spese di consumo.</p>

        <div class="grid gap-4 xl:grid-cols-2">
            <section class="panel p-5">
                <h2 class="font-display text-xl">Entrate e uscite</h2>
                <FinanceChart
                    class="mt-4"
                    type="bar"
                    :labels="report.income_expense.labels"
                    :series="[
                        { label: 'Entrate', data: report.income_expense.income, color: '#0f766e' },
                        { label: 'Spese', data: report.income_expense.expense, color: '#9f1239' },
                    ]"
                />
            </section>
            <section class="panel p-5">
                <h2 class="font-display text-xl">Risparmio mensile</h2>
                <FinanceChart class="mt-4" type="bar" :labels="report.savings.labels" :series="[{ label: 'Risparmio', data: report.savings.values, color: '#115e59' }]" />
            </section>
            <section class="panel p-5">
                <h2 class="font-display text-xl">Spese per categoria</h2>
                <FinanceChart
                    v-if="report.categories.labels.length"
                    class="mt-4"
                    type="doughnut"
                    :labels="report.categories.labels"
                    :series="[{ label: 'Spese', data: report.categories.values, color: '#0f766e' }]"
                />
                <p v-else class="mt-4 text-sm text-stone-500">Nessuna spesa contabilizzata in questo intervallo.</p>
            </section>
            <section class="panel p-5">
                <h2 class="font-display text-xl">Liquidità</h2>
                <FinanceChart class="mt-4" type="line" :labels="report.liquidity.labels" :series="[{ label: 'Liquidità', data: report.liquidity.values, color: '#0f766e' }]" />
            </section>
            <section class="panel p-5">
                <h2 class="font-display text-xl">Patrimonio</h2>
                <FinanceChart class="mt-4" type="line" :labels="report.net_worth.labels" :series="[{ label: 'Patrimonio', data: report.net_worth.values, color: '#44403c' }]" />
            </section>
            <section class="panel p-5">
                <h2 class="font-display text-xl">Confronto conti</h2>
                <FinanceChart
                    class="mt-4"
                    type="bar"
                    :labels="report.accounts.labels"
                    :series="[{ label: 'Saldo', data: report.accounts.values, color: '#115e59' }]"
                />
            </section>
        </div>

        <section class="panel mt-4 p-5">
            <h2 class="font-display text-xl">Costi bancari</h2>
            <p class="mt-2 text-sm">Ultimi 12 mesi contabilizzati: {{ report.bank_fees.trailing }}</p>
            <p class="text-sm">{{ report.bank_fees.note }}</p>
        </section>

        <section v-if="forecast" class="panel mt-4 p-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-wider text-amber-800 dark:text-amber-200">Stima</p>
                    <h2 class="font-display text-xl">Proiezione di cassa</h2>
                </div>
                <p class="text-sm text-stone-500">{{ report.forecast.note }}</p>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div>
                    <p class="text-xs text-stone-500">Liquidità attuale</p>
                    <p class="text-xl font-semibold tabular-nums">{{ forecast.opening }}</p>
                </div>
                <div>
                    <p class="text-xs text-stone-500">Liquidità prevista al {{ formatDate(report.forecast.until) }}</p>
                    <p class="text-xl font-semibold tabular-nums">{{ forecast.closing }}</p>
                </div>
                <div>
                    <p class="text-xs text-stone-500">Minimo previsto</p>
                    <p class="text-xl font-semibold tabular-nums">{{ forecast.minimum }}</p>
                    <p class="text-xs text-stone-500">{{ formatDate(forecast.minimum_on) }}</p>
                </div>
            </div>
            <p v-if="forecast.negative_ranges.length" class="mt-3 text-sm text-rose-800 dark:text-rose-200">
                Periodi sotto zero:
                <span v-for="range in forecast.negative_ranges" :key="range.from"> {{ formatDate(range.from) }} – {{ formatDate(range.until) }}.</span>
            </p>
            <p v-else class="mt-3 text-sm text-stone-500">Nessun saldo negativo previsto nell'intervallo.</p>
            <FinanceChart
                class="mt-4"
                type="line"
                :labels="forecast.points.map((point) => point.date)"
                :series="[{ label: 'Liquidità stimata', data: forecast.points.map((point) => point.minor), color: '#b45309' }]"
            />
            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                <p v-for="account in forecast.accounts" :key="account.id" class="text-sm">
                    {{ account.name }}: {{ account.opening }} → {{ account.closing }}
                </p>
            </div>
            <ul class="mt-4 divide-y divide-stone-100 text-sm dark:divide-white/10">
                <li v-for="(event, index) in forecast.events.slice(0, 12)" :key="index" class="flex justify-between gap-3 py-2">
                    <span>{{ formatDate(event.date) }} · {{ event.description }} <span class="text-stone-500">· stima</span></span>
                    <span class="tabular-nums">{{ event.delta < 0 ? '−' : '+' }}{{ event.amount }}</span>
                </li>
            </ul>
        </section>
    </AuthenticatedLayout>
</template>
