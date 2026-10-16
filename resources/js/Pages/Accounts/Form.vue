<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import SelectField from '@/Components/SelectField.vue';
import { Option } from '@/types/finance';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    account: {
        id: number;
        name: string;
        institution: string | null;
        type: string;
        wealth_bucket: string;
        currency: string;
        color: string;
        icon: string;
        status: string;
        include_in_net_worth: boolean;
        notes: string | null;
        opening_balance: string;
        opening_balance_on: string;
    } | null;
    types: Option[];
    buckets: Option[];
    defaultBuckets: Record<string, string>;
    statuses: Option[];
    currencies: string[];
    icons: Option[];
    today: string;
}>();

const editing = computed(() => props.account !== null);

const bucketOptions = computed(() => [
    { value: '', label: `In base alla tipologia: ${props.defaultBuckets[form.type] ?? ''}` },
    ...props.buckets,
]);

const bucketHint = computed(() => {
    const chosen = form.wealth_bucket || props.buckets.find((bucket) => bucket.label === props.defaultBuckets[form.type])?.value;

    return props.buckets.find((bucket) => bucket.value === chosen)?.hint ?? '';
});

const form = useForm({
    name: props.account?.name ?? '',
    institution: props.account?.institution ?? '',
    type: props.account?.type ?? 'checking',
    wealth_bucket: props.account?.wealth_bucket ?? '',
    currency: props.account?.currency ?? 'EUR',
    opening_balance: '0,00',
    opening_balance_on: props.today,
    color: props.account?.color ?? '#115e59',
    icon: props.account?.icon ?? 'landmark',
    status: props.account?.status ?? 'active',
    include_in_net_worth: props.account?.include_in_net_worth ?? true,
    notes: props.account?.notes ?? '',
});

function submit(): void {
    if (props.account) {
        form.put(route('accounts.update', props.account.id));
        return;
    }

    form.post(route('accounts.store'));
}
</script>

<template>
    <Head :title="editing ? 'Modifica conto' : 'Nuovo conto'" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs uppercase tracking-[0.16em] text-stone-500">Conti</p>
                <h1 class="font-display text-2xl">{{ editing ? 'Modifica conto' : 'Nuovo conto' }}</h1>
            </div>
        </template>

        <form class="panel mx-auto max-w-3xl space-y-5 p-6" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="name">Nome</label>
                    <input id="name" v-model="form.name" class="field" required />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>
                <div>
                    <label class="label" for="institution">Istituto</label>
                    <input id="institution" v-model="form.institution" class="field" placeholder="Intesa Sanpaolo" />
                    <InputError class="mt-2" :message="form.errors.institution" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="type">Tipologia</label>
                    <SelectField id="type" v-model="form.type" :options="types" />
                </div>
                <div>
                    <label class="label" for="currency">Valuta</label>
                    <SelectField id="currency" v-model="form.currency" :disabled="editing" :options="currencies.map((currency) => ({ value: currency, label: currency }))" />
                    <p v-if="editing" class="mt-1 text-xs text-stone-500">La valuta resta quella dell'apertura.</p>
                </div>
            </div>

            <div>
                <label class="label" for="wealth_bucket">Sezione del patrimonio</label>
                <SelectField id="wealth_bucket" v-model="form.wealth_bucket" :options="bucketOptions" />
                <p class="mt-1 text-xs text-stone-500">{{ bucketHint }}</p>
                <InputError class="mt-2" :message="form.errors.wealth_bucket" />
            </div>

            <div v-if="!editing" class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="opening_balance">Saldo iniziale</label>
                    <input id="opening_balance" v-model="form.opening_balance" class="field" inputmode="decimal" required />
                    <p class="mt-1 text-xs text-stone-500">Anche 0,00. Diventa una scrittura, non un saldo separato.</p>
                    <InputError class="mt-2" :message="form.errors.opening_balance" />
                </div>
                <div>
                    <label class="label" for="opening_balance_on">Data del saldo</label>
                    <input id="opening_balance_on" v-model="form.opening_balance_on" type="date" class="field" required />
                    <InputError class="mt-2" :message="form.errors.opening_balance_on" />
                </div>
            </div>

            <div v-else class="rounded-xl bg-stone-50 px-4 py-3 text-sm text-stone-600 dark:bg-white/5 dark:text-stone-300">
                Saldo iniziale {{ account?.opening_balance }} al {{ account?.opening_balance_on }}. Per correggerlo usa una riconciliazione o uno storno.
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="label" for="icon">Icona</label>
                    <SelectField id="icon" v-model="form.icon" :options="icons" />
                </div>
                <div>
                    <label class="label" for="color">Colore</label>
                    <input id="color" v-model="form.color" type="color" class="mt-1 h-10 w-full rounded-xl border border-stone-300 bg-white dark:border-white/10" />
                </div>
                <div v-if="editing">
                    <label class="label" for="status">Stato</label>
                    <SelectField id="status" v-model="form.status" :options="statuses" />
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.include_in_net_worth" type="checkbox" class="rounded border-stone-300 text-teal-800" />
                Includi nel patrimonio netto
            </label>

            <div>
                <label class="label" for="notes">Note</label>
                <textarea id="notes" v-model="form.notes" rows="3" class="field" />
            </div>

            <div class="flex justify-end">
                <button class="btn-primary" :disabled="form.processing">{{ editing ? 'Salva' : 'Crea conto' }}</button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
