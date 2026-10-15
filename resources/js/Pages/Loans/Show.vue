<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { formatDate } from '@/utils/date';
import { AccountOption } from '@/types/finance';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    loan: {
        id: number;
        direction: string;
        direction_label: string;
        description: string;
        notes: string | null;
        counterparty: string;
        currency: string;
        opened_on: string;
        due_on: string | null;
        agreed_interest: string | null;
        disbursed: string;
        recovered: string;
        interest_received: string;
        residual: string;
        residual_minor: number;
        status_label: string;
        overdue: boolean;
        disbursements: { id: number; occurred_on: string; account: string; amount: string; notes: string | null; movement_id: number }[];
        repayments: {
            id: number;
            occurred_on: string;
            kind_label: string;
            account: string | null;
            principal: string;
            interest: string;
            notes: string | null;
            movement_id: number | null;
        }[];
    };
    accounts: AccountOption[];
    others: { id: number; label: string; direction: string }[];
    today: string;
}>();

const extra = useForm({
    amount: '',
    account_id: '' as number | '',
    occurred_on: props.today,
    notes: '',
});

const repayment = useForm({
    occurred_on: props.today,
    kind: 'cash',
    account_id: '' as number | '',
    notes: '',
    surplus_as: '' as '' | 'interest',
    allocations: [
        { loan_id: props.loan.id, principal: '', interest: '' },
    ] as { loan_id: number | ''; principal: string; interest: string }[],
});

const sameDirection = computed(() => props.others.filter((other) => other.direction === props.loan.direction));

function addAllocation(): void {
    repayment.allocations.push({ loan_id: '', principal: '', interest: '' });
}

function disburse(): void {
    extra.post(route('loans.disburse', props.loan.id));
}

function repay(): void {
    repayment.post(route('loans.repay'));
}
</script>

<template>
    <Head :title="loan.counterparty" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs uppercase tracking-[0.16em] text-stone-500">{{ loan.direction_label }}</p>
                <h1 class="font-display text-2xl">{{ loan.counterparty }}</h1>
            </div>
        </template>

        <div class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
            <div class="space-y-4">
                <section class="panel p-6">
                    <p class="text-xs uppercase tracking-wider text-stone-500">{{ loan.status_label }}</p>
                    <p class="mt-2 font-display text-4xl tabular-nums">{{ loan.residual }}</p>
                    <p class="mt-2 text-sm text-stone-500">Residuo su {{ loan.disbursed }} erogati. Recuperato {{ loan.recovered }}.</p>
                    <p v-if="loan.overdue" class="mt-2 text-sm text-rose-700 dark:text-rose-300">Scaduto il {{ formatDate(loan.due_on || '') }}.</p>
                    <p class="mt-3 text-sm">{{ loan.description }}</p>
                    <p class="mt-2 text-xs text-stone-500">
                        Aperto il {{ formatDate(loan.opened_on) }}
                        <span v-if="loan.due_on"> · scadenza {{ formatDate(loan.due_on) }}</span>
                        <span v-if="loan.agreed_interest"> · interessi concordati {{ loan.agreed_interest }}</span>
                    </p>
                    <p v-if="loan.notes" class="mt-2 text-sm">{{ loan.notes }}</p>
                    <p class="mt-3 text-sm">Interessi già registrati: {{ loan.interest_received }}. Solo questi contano come ricavo o costo, non il capitale.</p>
                </section>

                <section class="panel p-6">
                    <h2 class="font-display text-xl">Altra erogazione</h2>
                    <form class="mt-3 space-y-3" @submit.prevent="disburse">
                        <input v-model="extra.amount" class="field" placeholder="Importo" required />
                        <select v-model="extra.account_id" class="field" required>
                            <option value="">Conto</option>
                            <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                        </select>
                        <input v-model="extra.occurred_on" type="date" class="field" required />
                        <InputError :message="extra.errors.amount || extra.errors.account_id" />
                        <button class="btn-secondary" :disabled="extra.processing">Aggiungi</button>
                    </form>
                </section>
            </div>

            <div class="space-y-4">
                <section class="panel p-6">
                    <h2 class="font-display text-xl">Rimborso</h2>
                    <p class="mt-1 text-sm text-stone-500">Può arrivare su un conto diverso da quello dell'erogazione. Più prestiti si chiudono solo con allocazioni esplicite.</p>
                    <form class="mt-4 space-y-3" @submit.prevent="repay">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <select v-model="repayment.kind" class="field">
                                <option value="cash">Denaro</option>
                                <option value="compensation">Compensazione non monetaria</option>
                            </select>
                            <input v-model="repayment.occurred_on" type="date" class="field" required />
                        </div>
                        <select v-if="repayment.kind === 'cash'" v-model="repayment.account_id" class="field">
                            <option value="">Conto di incasso o pagamento</option>
                            <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                        </select>
                        <div v-for="(row, index) in repayment.allocations" :key="index" class="grid gap-2 sm:grid-cols-3">
                            <select v-model="row.loan_id" class="field">
                                <option :value="loan.id">{{ loan.counterparty }}</option>
                                <option v-for="other in sameDirection" :key="other.id" :value="other.id">{{ other.label }}</option>
                            </select>
                            <input v-model="row.principal" class="field" placeholder="Capitale" />
                            <input v-model="row.interest" class="field" placeholder="Interessi" />
                        </div>
                        <button v-if="sameDirection.length" type="button" class="text-sm font-semibold text-teal-800 dark:text-teal-300" @click="addAllocation">
                            Alloca anche un altro prestito
                        </button>
                        <label v-if="repayment.kind === 'cash'" class="flex items-center gap-2 text-sm">
                            <input v-model="repayment.surplus_as" true-value="interest" false-value="" type="checkbox" class="rounded border-stone-300 text-teal-800" />
                            L'eccedenza rispetto al residuo è interesse, non capitale
                        </label>
                        <textarea v-model="repayment.notes" rows="2" class="field" placeholder="Note" />
                        <InputError :message="repayment.errors.account_id || repayment.errors.allocations || repayment.errors['allocations.0.principal']" />
                        <button class="btn-primary" :disabled="repayment.processing">Registra rimborso</button>
                    </form>
                </section>

                <section class="panel p-6">
                    <h2 class="font-display text-xl">Erogazioni</h2>
                    <ul class="mt-3 divide-y divide-stone-100 dark:divide-white/10">
                        <li v-for="row in loan.disbursements" :key="row.id" class="flex justify-between gap-3 py-2 text-sm">
                            <span>{{ formatDate(row.occurred_on) }} · {{ row.account }}</span>
                            <Link :href="route('movements.show', row.movement_id)" class="tabular-nums text-teal-800 dark:text-teal-300">{{ row.amount }}</Link>
                        </li>
                    </ul>
                </section>

                <section class="panel p-6">
                    <h2 class="font-display text-xl">Rimborsi</h2>
                    <p v-if="loan.repayments.length === 0" class="mt-2 text-sm text-stone-500">Nessun rimborso ancora.</p>
                    <ul v-else class="mt-3 divide-y divide-stone-100 dark:divide-white/10">
                        <li v-for="row in loan.repayments" :key="row.id" class="py-2 text-sm">
                            <div class="flex justify-between gap-3">
                                <span>{{ formatDate(row.occurred_on) }} · {{ row.kind_label }}<span v-if="row.account"> · {{ row.account }}</span></span>
                                <span class="tabular-nums">{{ row.principal }}</span>
                            </div>
                            <p class="text-xs text-stone-500">Capitale {{ row.principal }} · interessi {{ row.interest }}</p>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
