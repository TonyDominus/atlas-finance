<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import InputError from '@/Components/InputError.vue';
import SelectField from '@/Components/SelectField.vue';
import { formatDate } from '@/utils/date';
import { InvestmentTotals, Position } from '@/types/finance';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    positions: Position[];
    by_currency: InvestmentTotals[];
    trades: {
        id: number;
        side: string;
        side_label: string;
        symbol: string;
        account: string;
        traded_on: string;
        quantity: string;
        unit_price: string;
        gross: string;
        fee: string | null;
        result: string | null;
        movement_id: number;
        can_void: boolean;
    }[];
    instruments: { id: number; label: string; currency: string }[];
    accounts: { id: number; name: string; currency: string; bucket: string }[];
    today: string;
}>();

const form = useForm({
    side: 'buy',
    instrument_id: (props.instruments[0]?.id ?? '') as number | '',
    account_id: '' as number | '',
    traded_on: props.today,
    quantity: '',
    amount: '',
    fee: '',
    notes: '',
});

const currency = computed(() => props.instruments.find((item) => item.id === form.instrument_id)?.currency ?? null);
const accountOptions = computed(() =>
    props.accounts
        .filter((account) => currency.value === null || account.currency === currency.value)
        .sort((a, b) => Number(b.bucket === 'invested') - Number(a.bucket === 'invested'))
        .map((account) => ({ value: account.id, label: `${account.name} · ${account.currency}` })),
);

const unitPrice = computed(() => {
    const quantity = Number(form.quantity.replace(/\./g, '').replace(',', '.'));
    const amount = Number(form.amount.replace(/\./g, '').replace(',', '.'));

    if (!quantity || !amount) {
        return null;
    }

    return (amount / quantity).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
});

function submit(): void {
    form.post(route('investments.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('quantity', 'amount', 'fee', 'notes'),
    });
}

function cancel(id: number): void {
    if (window.confirm('Annullare questa operazione? Viene registrato uno storno, anche della commissione collegata.')) {
        router.post(route('investments.void', id), {}, { preserveScroll: true });
    }
}

function tone(minor: number | null): string {
    if (minor === null || minor === 0) return '';
    return minor > 0 ? 'text-teal-800 dark:text-teal-300' : 'text-rose-800 dark:text-rose-300';
}
</script>

<template>
    <Head title="Investimenti" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.16em] text-stone-500">Titoli e quantità</p>
                    <h1 class="font-display text-2xl">Investimenti</h1>
                </div>
                <Link :href="route('market.index')" class="btn-secondary">Mercato</Link>
            </div>
        </template>

        <div class="space-y-6">
            <section v-for="row in by_currency" :key="row.currency" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article class="panel p-5">
                    <p class="text-xs uppercase tracking-wider text-stone-500">Costo in {{ row.currency }}</p>
                    <p class="mt-2 font-display text-3xl tabular-nums">{{ row.ledger }}</p>
                    <p class="mt-2 text-xs text-stone-500">Quanto è stato pagato per i titoli ancora in portafoglio.</p>
                </article>
                <article class="panel p-5">
                    <p class="text-xs uppercase tracking-wider text-stone-500">Valore stimato</p>
                    <p class="mt-2 font-display text-3xl tabular-nums">{{ row.value }}</p>
                    <p class="mt-2 text-xs text-stone-500">
                        <template v-if="row.unpriced_count">{{ row.unpriced_count }} senza prezzo, contati al costo.</template>
                        <template v-else>Ultimo prezzo disponibile per ogni titolo.</template>
                    </p>
                </article>
                <article class="panel p-5">
                    <p class="text-xs uppercase tracking-wider text-stone-500">Non realizzato</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums" :class="tone(row.unrealized_minor)">{{ row.unrealized }}</p>
                    <p class="mt-2 text-xs text-stone-500">Solo sui titoli con prezzo. Non entra nel ledger finché non vendi.</p>
                </article>
                <article class="panel p-5">
                    <p class="text-xs uppercase tracking-wider text-stone-500">Realizzato</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums" :class="tone(row.realized_minor)">{{ row.realized }}</p>
                    <p class="mt-2 text-xs text-stone-500">Vendite meno costo medio. Fuori da entrate e spese del mese.</p>
                </article>
            </section>

            <section class="panel p-5">
                <h2 class="font-display text-xl">Posizioni</h2>
                <EmptyState
                    v-if="positions.length === 0"
                    class="mt-4"
                    title="Nessuna posizione aperta"
                    body="Registra un acquisto qui sotto. Il titolo si crea prima nella pagina Mercato."
                />
                <div v-else class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wider text-stone-500">
                            <tr>
                                <th class="py-2 pr-4">Titolo</th>
                                <th class="py-2 pr-4">Conto</th>
                                <th class="py-2 pr-4 text-right">Quantità</th>
                                <th class="py-2 pr-4 text-right">Prezzo medio</th>
                                <th class="py-2 pr-4 text-right">Costo</th>
                                <th class="py-2 pr-4 text-right">Ultimo prezzo</th>
                                <th class="py-2 pr-4 text-right">Valore</th>
                                <th class="py-2 text-right">Risultato</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100 dark:divide-white/10">
                            <tr v-for="position in positions" :key="`${position.instrument_id}-${position.account_id}`">
                                <td class="py-3 pr-4">
                                    <p class="font-semibold">{{ position.symbol }}</p>
                                    <p class="text-xs text-stone-500">{{ position.name }} · {{ position.kind_label }}</p>
                                </td>
                                <td class="py-3 pr-4">{{ position.account }}</td>
                                <td class="py-3 pr-4 text-right tabular-nums">{{ position.quantity }}</td>
                                <td class="py-3 pr-4 text-right tabular-nums">{{ position.average_price }}</td>
                                <td class="py-3 pr-4 text-right tabular-nums">{{ position.cost }}</td>
                                <td class="py-3 pr-4 text-right tabular-nums">
                                    <template v-if="position.price">
                                        {{ position.price }}
                                        <p class="text-xs text-stone-500">{{ formatDate(position.priced_on || '') }} · {{ position.price_source }}</p>
                                    </template>
                                    <span v-else class="text-stone-400">nessuno</span>
                                </td>
                                <td class="py-3 pr-4 text-right tabular-nums">{{ position.value ?? '—' }}</td>
                                <td class="py-3 text-right tabular-nums" :class="tone(position.unrealized_minor)">
                                    <template v-if="position.unrealized">
                                        {{ position.unrealized }}
                                        <p class="text-xs">{{ position.unrealized_percent?.toLocaleString('it-IT') }}%</p>
                                    </template>
                                    <template v-else>—</template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-for="row in by_currency.filter((item) => item.unassigned_minor !== 0)" :key="row.currency" class="mt-3 text-xs text-stone-500">
                    {{ row.unassigned }} di investimenti registrati senza titolo, da movimenti generici. Restano al costo.
                </p>
            </section>

            <div class="grid gap-6 xl:grid-cols-[0.8fr_1.2fr]">
                <form class="panel space-y-3 p-6" @submit.prevent="submit">
                    <h2 class="font-display text-xl">Nuova operazione</h2>
                    <div v-if="instruments.length === 0" class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:bg-amber-950 dark:text-amber-100">
                        Prima aggiungi il titolo nella pagina <Link :href="route('market.index')" class="underline">Mercato</Link>.
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="label" for="side">Tipo</label>
                            <SelectField
                                id="side"
                                v-model="form.side"
                                :options="[
                                    { value: 'buy', label: 'Acquisto' },
                                    { value: 'sell', label: 'Vendita' },
                                ]"
                            />
                        </div>
                        <div>
                            <label class="label" for="traded_on">Data</label>
                            <input id="traded_on" v-model="form.traded_on" type="date" class="field" :max="today" required />
                            <InputError class="mt-2" :message="form.errors.traded_on" />
                        </div>
                    </div>
                    <div>
                        <label class="label" for="instrument_id">Titolo</label>
                        <SelectField id="instrument_id" v-model="form.instrument_id" required :options="instruments.map((item) => ({ value: item.id, label: item.label }))" />
                        <InputError class="mt-2" :message="form.errors.instrument_id" />
                    </div>
                    <div>
                        <label class="label" for="account_id">Conto di regolamento</label>
                        <SelectField id="account_id" v-model="form.account_id" required :options="[{ value: '', label: 'Seleziona' }, ...accountOptions]" />
                        <p class="mt-1 text-xs text-stone-500">Il conto da cui escono o in cui rientrano i soldi. Stessa valuta del titolo.</p>
                        <InputError class="mt-2" :message="form.errors.account_id" />
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="label" for="quantity">Quantità</label>
                            <input id="quantity" v-model="form.quantity" class="field" inputmode="decimal" placeholder="10,5" required />
                            <InputError class="mt-2" :message="form.errors.quantity" />
                        </div>
                        <div>
                            <label class="label" for="amount">{{ form.side === 'buy' ? 'Controvalore pagato' : 'Controvalore incassato' }}</label>
                            <input id="amount" v-model="form.amount" class="field" inputmode="decimal" placeholder="1.050,00" required />
                            <p v-if="unitPrice" class="mt-1 text-xs text-stone-500">Prezzo unitario {{ unitPrice }}</p>
                            <InputError class="mt-2" :message="form.errors.amount" />
                        </div>
                    </div>
                    <div>
                        <label class="label" for="fee">Commissione</label>
                        <input id="fee" v-model="form.fee" class="field" inputmode="decimal" placeholder="0,00" />
                        <p class="mt-1 text-xs text-stone-500">Registrata come commissione bancaria separata, non nel costo del titolo.</p>
                        <InputError class="mt-2" :message="form.errors.fee" />
                    </div>
                    <textarea v-model="form.notes" rows="2" class="field" placeholder="Note" />
                    <button class="btn-primary" :disabled="form.processing || instruments.length === 0">Registra</button>
                </form>

                <section class="panel p-6">
                    <h2 class="font-display text-xl">Operazioni</h2>
                    <p v-if="trades.length === 0" class="mt-3 text-sm text-stone-500">Nessuna operazione registrata.</p>
                    <ul v-else class="mt-3 divide-y divide-stone-100 dark:divide-white/10">
                        <li v-for="trade in trades" :key="trade.id" class="flex flex-wrap items-start justify-between gap-3 py-3 text-sm">
                            <div>
                                <p class="font-medium">{{ trade.side_label }} {{ trade.quantity }} {{ trade.symbol }}</p>
                                <p class="text-xs text-stone-500">
                                    {{ formatDate(trade.traded_on) }} · {{ trade.account }} · prezzo {{ trade.unit_price }}
                                    <span v-if="trade.fee"> · commissione {{ trade.fee }}</span>
                                    <span v-if="trade.result"> · risultato {{ trade.result }}</span>
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="tabular-nums font-semibold">{{ trade.gross }}</p>
                                <Link :href="route('movements.show', trade.movement_id)" class="text-xs text-teal-800 dark:text-teal-300">Movimento</Link>
                                <button v-if="trade.can_void" type="button" class="ml-2 text-xs text-rose-800 dark:text-rose-300" @click="cancel(trade.id)">Annulla</button>
                            </div>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
