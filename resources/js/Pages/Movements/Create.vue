<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SelectField from '@/Components/SelectField.vue';
import InputError from '@/Components/InputError.vue';
import { AccountOption, CategoryOption, CounterpartyOption, Option } from '@/types/finance';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    accounts: AccountOption[];
    categories: CategoryOption[];
    counterparties: CounterpartyOption[];
    types: Option[];
    prefillAccountId: number | null;
    today: string;
}>();

const advanced = ref(false);

const form = useForm({
    type: 'expense',
    amount: '',
    occurred_on: props.today,
    booked_on: '',
    description: '',
    account_id: (props.prefillAccountId ?? '') as number | '',
    destination_account_id: '' as number | '',
    fee_amount: '',
    category_id: '' as number | '',
    counterparty_id: '' as number | '',
    counterparty_name: '',
    notes: '',
    tags: '',
    external_id: '',
    as_forecast: false,
    attachments: [] as File[],
});

const hint = computed(() => props.types.find((type) => type.value === form.type)?.hint ?? '');
const categoryKind = computed(() => (form.type === 'income' ? 'income' : 'expense'));
const visibleCategories = computed(() => {
    if (['transfer', 'investment_buy', 'investment_sell'].includes(form.type)) {
        return [];
    }

    return props.categories.filter((category) => category.kind === categoryKind.value);
});

function onFiles(event: Event): void {
    const input = event.target as HTMLInputElement;
    form.attachments = input.files ? Array.from(input.files) : [];
}

function submit(): void {
    form.post(route('movements.store'), { forceFormData: true });
}
</script>

<template>
    <Head title="Nuovo movimento" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs uppercase tracking-[0.16em] text-stone-500">Ledger</p>
                <h1 class="font-display text-2xl">Nuovo movimento</h1>
            </div>
        </template>

        <form class="panel mx-auto max-w-2xl space-y-5 p-6" @submit.prevent="submit">
            <div v-if="accounts.length === 0" class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:bg-amber-950 dark:text-amber-100">
                Prima crea un conto attivo.
            </div>

            <div>
                <label class="label" for="type">Tipo</label>
                <SelectField id="type" v-model="form.type" :options="types" />
                <p class="mt-1 text-xs text-stone-500">{{ hint }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="amount">Importo</label>
                    <input id="amount" v-model="form.amount" class="field" placeholder="12,50" required />
                    <InputError class="mt-2" :message="form.errors.amount" />
                </div>
                <div>
                    <label class="label" for="occurred_on">Data</label>
                    <input id="occurred_on" v-model="form.occurred_on" type="date" class="field" required />
                </div>
            </div>

            <div>
                <label class="label" for="description">Descrizione</label>
                <input id="description" v-model="form.description" class="field" required />
                <InputError class="mt-2" :message="form.errors.description" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="account_id">Conto</label>
                    <SelectField
                        id="account_id"
                        v-model="form.account_id"
                        required
                        :options="[{ value: '', label: 'Seleziona' }, ...accounts.map((account) => ({ value: account.id, label: `${account.name} · ${account.currency}` }))]"
                    />
                    <InputError class="mt-2" :message="form.errors.account_id" />
                </div>
                <div v-if="form.type === 'transfer'">
                    <label class="label" for="destination_account_id">Destinazione</label>
                    <SelectField
                        id="destination_account_id"
                        v-model="form.destination_account_id"
                        :options="[{ value: '', label: 'Seleziona' }, ...accounts.map((account) => ({ value: account.id, label: account.name }))]"
                    />
                    <InputError class="mt-2" :message="form.errors.destination_account_id" />
                </div>
                <div v-else-if="visibleCategories.length">
                    <label class="label" for="category_id">Categoria</label>
                    <SelectField
                        id="category_id"
                        v-model="form.category_id"
                        :options="[{ value: '', label: 'Proponi in automatico' }, ...visibleCategories.map((category) => ({ value: category.id, label: `${category.parent_id ? '– ' : ''}${category.name}` }))]"
                    />
                </div>
            </div>

            <div>
                <button type="button" class="text-sm font-semibold text-teal-800 dark:text-teal-300" @click="advanced = !advanced">
                    {{ advanced ? 'Nascondi avanzate' : 'Avanzate' }}
                </button>
            </div>

            <div v-if="advanced" class="space-y-4 border-t border-stone-100 pt-4 dark:border-white/10">
                <div v-if="form.type === 'transfer'">
                    <label class="label" for="fee_amount">Commissione separata</label>
                    <input id="fee_amount" v-model="form.fee_amount" class="field" placeholder="0,00" />
                    <p class="mt-1 text-xs text-stone-500">Resta una spesa distinta. Il trasferimento da solo non cambia il patrimonio.</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="booked_on">Data contabile</label>
                        <input id="booked_on" v-model="form.booked_on" type="date" class="field" />
                        <p class="mt-1 text-xs text-stone-500">Se vuota, coincide con la data operazione.</p>
                    </div>
                    <div>
                        <label class="label" for="counterparty_id">Controparte</label>
                        <SelectField
                            id="counterparty_id"
                            v-model="form.counterparty_id"
                            :options="[{ value: '', label: 'Nessuna' }, ...counterparties.map((person) => ({ value: person.id, label: person.name }))]"
                        />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="counterparty_name">Nuova controparte</label>
                        <input id="counterparty_name" v-model="form.counterparty_name" class="field" placeholder="Solo se non è già in elenco" />
                    </div>
                    <div>
                        <label class="label" for="tags">Tag</label>
                        <input id="tags" v-model="form.tags" class="field" placeholder="casa, urgente" />
                    </div>
                </div>

                <div>
                    <label class="label" for="notes">Note</label>
                    <textarea id="notes" v-model="form.notes" rows="3" class="field" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="external_id">Identificativo esterno</label>
                        <input id="external_id" v-model="form.external_id" class="field" placeholder="Per evitare duplicati in import" />
                        <InputError class="mt-2" :message="form.errors.external_id" />
                    </div>
                    <div>
                        <label class="label" for="attachments">Allegati</label>
                        <input id="attachments" type="file" multiple class="mt-1 block w-full text-sm" @change="onFiles" />
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.as_forecast" type="checkbox" class="rounded border-stone-300 text-teal-800" />
                    Solo previsione: non muove il saldo finché non la contabilizzi
                </label>
            </div>

            <div class="flex justify-end">
                <button class="btn-primary" :disabled="form.processing || accounts.length === 0">Registra</button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
