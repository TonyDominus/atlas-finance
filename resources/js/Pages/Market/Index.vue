<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FinanceChart from '@/Components/FinanceChart.vue';
import InputError from '@/Components/InputError.vue';
import SelectField from '@/Components/SelectField.vue';
import { formatDate } from '@/utils/date';
import { Option } from '@/types/finance';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface MarketRow {
    id: number;
    symbol: string;
    name: string;
    isin: string | null;
    kind: string;
    kind_label: string;
    currency: string;
    exchange: string | null;
    quote_symbol: string | null;
    watch: boolean;
    notes: string | null;
    held: boolean;
    quantity: string | null;
    price: string | null;
    priced_on: string | null;
    source: string | null;
    change_percent: number | null;
    previous_on: string | null;
    points: { date: string; price: number }[];
}

const props = defineProps<{
    instruments: MarketRow[];
    kinds: Option[];
    currencies: string[];
    provider: { name: string; configured: boolean; url: string };
    today: string;
}>();

const selectedId = ref<number | null>(props.instruments.find((row) => row.points.length > 1)?.id ?? props.instruments[0]?.id ?? null);
const selected = computed(() => props.instruments.find((row) => row.id === selectedId.value) ?? null);
const editing = ref<number | null>(null);

const blank = {
    symbol: '',
    name: '',
    isin: '',
    kind: 'etf',
    currency: 'EUR',
    exchange: '',
    quote_symbol: '',
    watch: true,
    notes: '',
};

const form = useForm({ ...blank });

const price = useForm({
    price: '',
    priced_on: props.today,
});

const refreshing = ref(false);

function edit(row: MarketRow): void {
    editing.value = row.id;
    form.defaults({
        symbol: row.symbol,
        name: row.name,
        isin: row.isin ?? '',
        kind: row.kind,
        currency: row.currency,
        exchange: row.exchange ?? '',
        quote_symbol: row.quote_symbol ?? '',
        watch: row.watch,
        notes: row.notes ?? '',
    });
    form.reset();
    form.clearErrors();
}

function stopEditing(): void {
    editing.value = null;
    form.defaults({ ...blank });
    form.reset();
    form.clearErrors();
}

function save(): void {
    if (editing.value) {
        form.put(route('market.update', editing.value), { preserveScroll: true, onSuccess: stopEditing });
        return;
    }

    form.post(route('market.store'), { preserveScroll: true, onSuccess: () => form.reset() });
}

function savePrice(): void {
    if (!selected.value) {
        return;
    }

    price.post(route('market.price', selected.value.id), {
        preserveScroll: true,
        onSuccess: () => price.reset('price'),
    });
}

function refresh(): void {
    refreshing.value = true;
    router.post(route('market.refresh'), {}, { preserveScroll: true, onFinish: () => (refreshing.value = false) });
}

function history(id: number): void {
    router.post(route('market.history', id), {}, { preserveScroll: true });
}

function remove(row: MarketRow): void {
    if (window.confirm(`Eliminare ${row.symbol} e i suoi prezzi salvati?`)) {
        router.delete(route('market.destroy', row.id), { preserveScroll: true });
    }
}

function tone(value: number | null): string {
    if (value === null || value === 0) return 'text-stone-500';
    return value > 0 ? 'text-teal-800 dark:text-teal-300' : 'text-rose-800 dark:text-rose-300';
}
</script>

<template>
    <Head title="Mercato" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.16em] text-stone-500">Quotazioni con fonte e data</p>
                    <h1 class="font-display text-2xl">Mercato</h1>
                </div>
                <button type="button" class="btn-primary" :disabled="!provider.configured || refreshing || instruments.length === 0" @click="refresh">
                    {{ refreshing ? 'Aggiorno…' : 'Aggiorna quotazioni' }}
                </button>
            </div>
        </template>

        <div class="space-y-6">
            <div
                class="rounded-2xl border px-4 py-3 text-sm"
                :class="provider.configured
                    ? 'border-teal-200 bg-teal-50 text-teal-950 dark:border-teal-900 dark:bg-teal-950 dark:text-teal-100'
                    : 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100'"
            >
                <template v-if="provider.configured">
                    Fonte automatica: <a :href="provider.url" target="_blank" rel="noopener" class="underline">{{ provider.name }}</a>, chiusure giornaliere,
                    non in tempo reale. Il piano gratuito ha un limite di richieste al giorno.
                </template>
                <template v-else>
                    Nessuna fonte automatica configurata: i prezzi si inseriscono a mano. Per l'aggiornamento automatico imposta
                    <code class="rounded bg-white/60 px-1 dark:bg-black/30">ALPHAVANTAGE_KEY</code> nel file .env, con una chiave gratuita di
                    <a :href="provider.url" target="_blank" rel="noopener" class="underline">{{ provider.name }}</a>.
                </template>
                Ogni prezzo mostra data e origine. Senza un prezzo, il titolo resta valutato al costo.
            </div>

            <div class="grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
                <section class="panel min-w-0 p-5">
                    <h2 class="font-display text-xl">Titoli seguiti</h2>
                    <EmptyState
                        v-if="instruments.length === 0"
                        class="mt-4"
                        title="Nessun titolo"
                        body="Aggiungi un'azione o un ETF con il suo simbolo. Poi puoi registrarne l'acquisto in Investimenti o seguirne solo il prezzo."
                    />
                    <div v-else class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs uppercase tracking-wider text-stone-500">
                                <tr>
                                    <th class="py-2 pr-4">Titolo</th>
                                    <th class="py-2 pr-4 text-right">Prezzo</th>
                                    <th class="py-2 pr-4 text-right">Variazione</th>
                                    <th class="py-2 pr-4">Fonte</th>
                                    <th class="py-2 pr-4 text-right">In portafoglio</th>
                                    <th class="py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100 dark:divide-white/10">
                                <tr
                                    v-for="row in instruments"
                                    :key="row.id"
                                    class="cursor-pointer"
                                    :class="row.id === selectedId ? 'bg-teal-50/60 dark:bg-white/5' : ''"
                                    @click="selectedId = row.id"
                                >
                                    <td class="py-3 pr-4">
                                        <p class="font-semibold">{{ row.symbol }} <span v-if="!row.watch && !row.held" class="text-xs font-normal text-stone-400">non seguito</span></p>
                                        <p class="text-xs text-stone-500">{{ row.name }} · {{ row.kind_label }} · {{ row.currency }}<span v-if="row.exchange"> · {{ row.exchange }}</span></p>
                                    </td>
                                    <td class="py-3 pr-4 text-right tabular-nums">
                                        <template v-if="row.price">{{ row.price }} <span class="text-xs text-stone-500">{{ row.currency }}</span></template>
                                        <span v-else class="text-stone-400">nessun prezzo</span>
                                    </td>
                                    <td class="py-3 pr-4 text-right tabular-nums" :class="tone(row.change_percent)">
                                        <template v-if="row.change_percent !== null">
                                            {{ row.change_percent > 0 ? '+' : '' }}{{ row.change_percent.toLocaleString('it-IT') }}%
                                            <p class="text-xs text-stone-500">dal {{ formatDate(row.previous_on || '') }}</p>
                                        </template>
                                        <template v-else>—</template>
                                    </td>
                                    <td class="py-3 pr-4 text-xs text-stone-500">
                                        <template v-if="row.priced_on">{{ formatDate(row.priced_on) }}<br />{{ row.source }}</template>
                                    </td>
                                    <td class="py-3 pr-4 text-right tabular-nums">{{ row.quantity ?? '—' }}</td>
                                    <td class="py-3 text-right text-xs whitespace-nowrap">
                                        <button type="button" class="text-teal-800 dark:text-teal-300" @click.stop="edit(row)">Modifica</button>
                                        <button v-if="!row.held" type="button" class="ml-2 text-rose-800 dark:text-rose-300" @click.stop="remove(row)">Elimina</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <form class="panel min-w-0 space-y-3 p-6" @submit.prevent="save">
                    <div class="flex items-center justify-between">
                        <h2 class="font-display text-xl">{{ editing ? 'Modifica titolo' : 'Aggiungi titolo' }}</h2>
                        <button v-if="editing" type="button" class="text-sm text-stone-500" @click="stopEditing">Annulla</button>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="label" for="symbol">Simbolo</label>
                            <input id="symbol" v-model="form.symbol" class="field uppercase" placeholder="VWCE" required />
                            <InputError class="mt-2" :message="form.errors.symbol" />
                        </div>
                        <div>
                            <label class="label" for="kind">Tipo</label>
                            <SelectField id="kind" v-model="form.kind" :options="kinds" />
                        </div>
                    </div>
                    <div>
                        <label class="label" for="name">Nome</label>
                        <input id="name" v-model="form.name" class="field" placeholder="Vanguard FTSE All-World" required />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="label" for="currency">Valuta di quotazione</label>
                            <SelectField id="currency" v-model="form.currency" :options="currencies.map((code) => ({ value: code, label: code }))" />
                            <InputError class="mt-2" :message="form.errors.currency" />
                        </div>
                        <div>
                            <label class="label" for="exchange">Borsa</label>
                            <input id="exchange" v-model="form.exchange" class="field" placeholder="Xetra" />
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="label" for="isin">ISIN</label>
                            <input id="isin" v-model="form.isin" class="field uppercase" placeholder="IE00BK5BQT80" />
                            <InputError class="mt-2" :message="form.errors.isin" />
                        </div>
                        <div>
                            <label class="label" for="quote_symbol">Simbolo per il fornitore</label>
                            <input id="quote_symbol" v-model="form.quote_symbol" class="field" placeholder="VWCE.DEX" />
                        </div>
                    </div>
                    <p class="text-xs text-stone-500">
                        Alpha Vantage usa simboli con suffisso per le borse europee, per esempio VWCE.DEX per Xetra. Se resta vuoto si usa il simbolo.
                    </p>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.watch" type="checkbox" class="rounded border-stone-300 text-teal-800" />
                        Segui il prezzo anche senza averlo in portafoglio
                    </label>
                    <button class="btn-primary" :disabled="form.processing">{{ editing ? 'Salva' : 'Aggiungi' }}</button>
                </form>
            </div>

            <section v-if="selected" class="panel p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-display text-xl">{{ selected.symbol }} · {{ selected.name }}</h2>
                        <p class="text-xs text-stone-500">
                            Prezzi in {{ selected.currency }}. Un prezzo per giorno: se due fonti danno un valore diverso, vale l'ultimo salvato.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button v-if="provider.configured" type="button" class="btn-secondary" @click="history(selected.id)">Scarica storico</button>
                        <Link v-if="selected.held" :href="route('investments.index')" class="btn-secondary">Posizione</Link>
                    </div>
                </div>

                <div class="mt-4 grid gap-6 xl:grid-cols-[1.4fr_0.6fr]">
                    <div class="min-w-0">
                        <FinanceChart
                            v-if="selected.points.length > 1"
                            type="line"
                            :divisor="1"
                            :fill="false"
                            :labels="selected.points.map((point) => formatDate(point.date))"
                            :series="[{ label: `${selected.symbol} (${selected.currency})`, data: selected.points.map((point) => point.price), color: '#0f766e' }]"
                        />
                        <p v-else class="rounded-xl bg-stone-50 px-4 py-6 text-center text-sm text-stone-500 dark:bg-white/5">
                            Servono almeno due prezzi per il grafico.
                            <template v-if="provider.configured">Usa «Scarica storico» o inserisci i prezzi a mano.</template>
                            <template v-else>Inseriscili a mano qui accanto.</template>
                        </p>
                    </div>

                    <form class="space-y-3" @submit.prevent="savePrice">
                        <h3 class="font-semibold">Prezzo inserito a mano</h3>
                        <p class="text-xs text-stone-500">Per esempio la chiusura letta sul sito della borsa o dell'emittente.</p>
                        <input v-model="price.price" class="field" inputmode="decimal" placeholder="118,42" required />
                        <InputError :message="price.errors.price" />
                        <input v-model="price.priced_on" type="date" class="field" :max="today" required />
                        <InputError :message="price.errors.priced_on" />
                        <button class="btn-secondary" :disabled="price.processing">Salva prezzo</button>
                    </form>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
