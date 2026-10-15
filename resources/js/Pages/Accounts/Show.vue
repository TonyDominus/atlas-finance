<script setup lang="ts">
import AccountGlyph from '@/Components/AccountGlyph.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import InputError from '@/Components/InputError.vue';
import Pagination from '@/Components/Pagination.vue';
import { formatDate } from '@/utils/date';
import { AccountCard, MovementSummary, Paginator } from '@/types/finance';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    account: AccountCard;
    movements: Paginator<MovementSummary>;
    today: string;
    fees: {
        id: number;
        description: string;
        amount: string;
        frequency_label: string;
        status: string;
        status_label: string;
        next_on: string | null;
    }[];
    reconciliation: {
        reconciled_on: string;
        statement: string;
        computed: string;
        difference: string;
        adjusted: boolean;
        notes: string | null;
    } | null;
}>();

const form = useForm({
    statement_balance: '',
    reconciled_on: props.today,
    post_adjustment: false,
    notes: '',
});

function submit(): void {
    form.post(route('accounts.reconcile', props.account.id));
}

const fee = useForm({
    description: 'Canone',
    kind: 'fee',
    purpose: 'account_fee',
    amount: '',
    amount_is_estimate: false,
    account_id: props.account.id,
    frequency: 'monthly',
    interval: 1,
    starts_on: props.today,
    category_id: '',
    ends_on: '',
    max_occurrences: '',
    reminder_days: '',
    notes: '',
});

function saveFee(): void {
    fee.post(route('recurrences.store'), { preserveScroll: true });
}
</script>

<template>
    <Head :title="account.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <AccountGlyph :name="account.icon" :color="account.color" />
                    <div>
                        <p class="text-xs uppercase tracking-[0.16em] text-stone-500">{{ account.type_label }}</p>
                        <h1 class="font-display text-2xl">{{ account.name }}</h1>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('movements.create', { account: account.id })" class="btn-primary">Movimento</Link>
                    <Link :href="route('accounts.edit', account.id)" class="btn-secondary">Modifica</Link>
                </div>
            </div>
        </template>

        <div class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
            <div class="space-y-4">
                <section class="panel p-6">
                    <p class="text-xs uppercase tracking-wider text-stone-500">Saldo calcolato</p>
                    <p class="mt-2 font-display text-4xl tabular-nums">{{ account.balance }}</p>
                    <p class="mt-3 text-sm text-stone-500">
                        Apertura {{ account.opening_balance }} il {{ formatDate(account.opening_balance_on) }}.
                        <span v-if="account.institution"> {{ account.institution }}.</span>
                    </p>
                    <p v-if="account.notes" class="mt-3 text-sm">{{ account.notes }}</p>
                </section>

                <section class="panel p-6">
                    <h2 class="font-display text-xl">Riconciliazione</h2>
                    <p class="mt-1 text-sm text-stone-500">
                        Confronta il ledger con l'estratto. La rettifica è una scrittura esplicita, non una modifica silenziosa del saldo.
                    </p>
                    <div v-if="reconciliation" class="mt-4 rounded-xl bg-stone-50 p-3 text-sm dark:bg-white/5">
                        Ultimo controllo {{ formatDate(reconciliation.reconciled_on) }}: estratto {{ reconciliation.statement }}, libro {{ reconciliation.computed }}, differenza {{ reconciliation.difference }}.
                        {{ reconciliation.adjusted ? 'Rettifica registrata.' : 'Nessuna rettifica automatica.' }}
                    </div>
                    <form class="mt-4 space-y-3" @submit.prevent="submit">
                        <div>
                            <label class="label" for="statement_balance">Saldo estratto</label>
                            <input id="statement_balance" v-model="form.statement_balance" class="field" required />
                            <InputError class="mt-2" :message="form.errors.statement_balance" />
                        </div>
                        <div>
                            <label class="label" for="reconciled_on">Data</label>
                            <input id="reconciled_on" v-model="form.reconciled_on" type="date" class="field" required />
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.post_adjustment" type="checkbox" class="rounded border-stone-300 text-teal-800" />
                            Registra una rettifica se c'è una differenza
                        </label>
                        <textarea v-model="form.notes" rows="2" class="field" placeholder="Note" />
                        <button class="btn-primary" :disabled="form.processing">Registra riconciliazione</button>
                    </form>
                </section>

                <section class="panel p-6">
                    <h2 class="font-display text-xl">Canoni</h2>
                    <p class="mt-1 text-sm text-stone-500">Restano previsioni. La scadenza non registra da sola il pagamento.</p>
                    <ul v-if="fees.length" class="mt-4 divide-y divide-stone-100 dark:divide-white/10">
                        <li v-for="item in fees" :key="item.id" class="flex items-start justify-between gap-3 py-3 text-sm">
                            <div>
                                <p class="font-medium">{{ item.description }}</p>
                                <p class="text-xs text-stone-500">
                                    {{ item.frequency_label }} · {{ item.status_label }}
                                    <span v-if="item.next_on"> · {{ formatDate(item.next_on) }}</span>
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="tabular-nums font-semibold">{{ item.amount }}</p>
                                <button v-if="item.status === 'active'" type="button" class="text-xs text-stone-500" @click="router.post(route('recurrences.status', item.id), { status: 'paused' })">Sospendi</button>
                                <Link :href="route('recurrences.destroy', item.id)" method="delete" as="button" class="ml-2 text-xs text-rose-800 dark:text-rose-300">Elimina</Link>
                            </div>
                        </li>
                    </ul>
                    <form class="mt-4 space-y-3" @submit.prevent="saveFee">
                        <input v-model="fee.description" class="field" required />
                        <input v-model="fee.amount" class="field" placeholder="2,90" required />
                        <select v-model="fee.frequency" class="field">
                            <option value="monthly">Mensile</option>
                            <option value="quarterly">Trimestrale</option>
                            <option value="semiannual">Semestrale</option>
                            <option value="yearly">Annuale</option>
                        </select>
                        <input v-model="fee.starts_on" type="date" class="field" required />
                        <InputError :message="fee.errors.amount || fee.errors.description" />
                        <button class="btn-secondary" :disabled="fee.processing">Aggiungi canone</button>
                    </form>
                </section>
            </div>

            <section class="panel p-6">
                <h2 class="font-display text-xl">Movimenti del conto</h2>
                <EmptyState
                    v-if="movements.data.length === 0"
                    title="Solo il saldo, per ora"
                    body="Se il saldo iniziale è zero e non hai ancora registrato operazioni, il conto resta vuoto."
                />
                <ul v-else class="mt-4 divide-y divide-stone-100 dark:divide-white/10">
                    <li v-for="movement in movements.data" :key="movement.id" class="flex items-start justify-between gap-3 py-3">
                        <div>
                            <Link :href="route('movements.show', movement.id)" class="font-medium" :class="{ 'line-through opacity-60': movement.voided }">
                                {{ movement.description }}
                            </Link>
                            <p class="text-xs text-stone-500">{{ movement.type_label }} · {{ formatDate(movement.booked_on) }}</p>
                        </div>
                        <p class="tabular-nums text-sm font-semibold">{{ movement.amount }}</p>
                    </li>
                </ul>
                <div class="mt-4">
                    <Pagination :links="movements.links" />
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
