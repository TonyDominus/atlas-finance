<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { AccountOption, CategoryOption, CounterpartyOption, Option } from '@/types/finance';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    rule: {
        id: number;
        description: string;
        kind: string;
        amount: string;
        amount_is_estimate: boolean;
        account_id: number;
        destination_account_id: number | null;
        category_id: number | null;
        counterparty_id: number | null;
        frequency: string;
        interval: number;
        interval_unit: string;
        starts_on: string;
        ends_on: string | null;
        max_occurrences: number | null;
        reminder_days: number | null;
        notes: string | null;
    } | null;
    accounts: AccountOption[];
    categories: CategoryOption[];
    counterparties: CounterpartyOption[];
    kinds: Option[];
    frequencies: Option[];
    today: string;
    purpose: string;
}>();

const form = useForm({
    description: props.rule?.description ?? '',
    kind: props.rule?.kind ?? (props.purpose === 'account_fee' ? 'fee' : 'expense'),
    purpose: props.purpose,
    amount: props.rule?.amount ?? '',
    amount_is_estimate: props.rule?.amount_is_estimate ?? false,
    account_id: (props.rule?.account_id ?? '') as number | '',
    destination_account_id: (props.rule?.destination_account_id ?? '') as number | '',
    category_id: (props.rule?.category_id ?? '') as number | '',
    counterparty_id: (props.rule?.counterparty_id ?? '') as number | '',
    frequency: props.rule?.frequency ?? 'monthly',
    interval: props.rule?.interval ?? 1,
    interval_unit: props.rule?.interval_unit ?? 'month',
    starts_on: props.rule?.starts_on ?? props.today,
    ends_on: props.rule?.ends_on ?? '',
    max_occurrences: props.rule?.max_occurrences ?? '',
    reminder_days: props.rule?.reminder_days ?? '',
    notes: props.rule?.notes ?? '',
});

const categoryKind = computed(() => (form.kind === 'income' ? 'income' : 'expense'));
const visibleCategories = computed(() => props.categories.filter((category) => category.kind === categoryKind.value));

function submit(): void {
    if (props.rule) {
        form.put(route('recurrences.update', props.rule.id));
        return;
    }

    form.post(route('recurrences.store'));
}
</script>

<template>
    <Head :title="rule ? 'Modifica ricorrenza' : 'Nuova ricorrenza'" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs uppercase tracking-[0.16em] text-stone-500">Previsione</p>
                <h1 class="font-display text-2xl">{{ rule ? 'Modifica ricorrenza' : 'Nuova ricorrenza' }}</h1>
            </div>
        </template>

        <form class="panel mx-auto max-w-3xl space-y-4 p-6" @submit.prevent="submit">
            <p class="text-sm text-stone-500">Il giorno 31, nei mesi più corti, cade l'ultimo giorno disponibile. Poi torna al 31.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="description">Descrizione</label>
                    <input id="description" v-model="form.description" class="field" required />
                    <InputError class="mt-2" :message="form.errors.description" />
                </div>
                <div>
                    <label class="label" for="amount">Importo</label>
                    <input id="amount" v-model="form.amount" class="field" placeholder="2,90" required />
                    <InputError class="mt-2" :message="form.errors.amount" />
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.amount_is_estimate" type="checkbox" class="rounded border-stone-300 text-teal-800" />
                Importo stimato
            </label>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="kind">Tipo</label>
                    <select id="kind" v-model="form.kind" class="field">
                        <option v-for="kind in kinds" :key="kind.value" :value="kind.value">{{ kind.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="account_id">Conto</label>
                    <select id="account_id" v-model="form.account_id" class="field" required>
                        <option value="">Seleziona</option>
                        <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.account_id" />
                </div>
            </div>
            <div v-if="form.kind === 'transfer'">
                <label class="label" for="destination_account_id">Destinazione</label>
                <select id="destination_account_id" v-model="form.destination_account_id" class="field">
                    <option value="">Seleziona</option>
                    <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                </select>
                <InputError class="mt-2" :message="form.errors.destination_account_id" />
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="label" for="frequency">Frequenza</label>
                    <select id="frequency" v-model="form.frequency" class="field">
                        <option v-for="frequency in frequencies" :key="frequency.value" :value="frequency.value">{{ frequency.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="interval">Ogni</label>
                    <input id="interval" v-model="form.interval" type="number" min="1" class="field" />
                </div>
                <div v-if="form.frequency === 'custom'">
                    <label class="label" for="interval_unit">Unità</label>
                    <select id="interval_unit" v-model="form.interval_unit" class="field">
                        <option value="day">Giorni</option>
                        <option value="week">Settimane</option>
                        <option value="month">Mesi</option>
                    </select>
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="starts_on">Data iniziale</label>
                    <input id="starts_on" v-model="form.starts_on" type="date" class="field" required />
                </div>
                <div>
                    <label class="label" for="ends_on">Data finale</label>
                    <input id="ends_on" v-model="form.ends_on" type="date" class="field" />
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div v-if="form.kind !== 'transfer'">
                    <label class="label" for="category_id">Categoria</label>
                    <select id="category_id" v-model="form.category_id" class="field">
                        <option value="">Nessuna</option>
                        <option v-for="category in visibleCategories" :key="category.id" :value="category.id">{{ category.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="counterparty_id">Controparte</label>
                    <select id="counterparty_id" v-model="form.counterparty_id" class="field">
                        <option value="">Nessuna</option>
                        <option v-for="person in counterparties" :key="person.id" :value="person.id">{{ person.name }}</option>
                    </select>
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="max_occurrences">Numero massimo</label>
                    <input id="max_occurrences" v-model="form.max_occurrences" type="number" min="1" class="field" placeholder="Vuoto = senza limite" />
                    <p class="mt-1 text-xs text-stone-500">Metti 1 per un pagamento previsto una sola volta.</p>
                </div>
                <div>
                    <label class="label" for="reminder_days">Promemoria, giorni prima</label>
                    <input id="reminder_days" v-model="form.reminder_days" type="number" min="0" max="60" class="field" />
                </div>
            </div>
            <div>
                <label class="label" for="notes">Note</label>
                <textarea id="notes" v-model="form.notes" rows="3" class="field" />
            </div>
            <div class="flex justify-end">
                <button class="btn-primary" :disabled="form.processing">Salva previsione</button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
