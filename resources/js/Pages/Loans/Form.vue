<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SelectField from '@/Components/SelectField.vue';
import InputError from '@/Components/InputError.vue';
import { AccountOption, CounterpartyOption } from '@/types/finance';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    accounts: AccountOption[];
    counterparties: CounterpartyOption[];
    today: string;
}>();

const form = useForm({
    direction: 'given',
    counterparty_id: '' as number | '',
    counterparty_name: '',
    amount: '',
    account_id: '' as number | '',
    opened_on: props.today,
    due_on: '',
    description: '',
    notes: '',
    agreed_interest: '',
});

function submit(): void {
    form.post(route('loans.store'));
}
</script>

<template>
    <Head title="Nuovo prestito" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs uppercase tracking-[0.16em] text-stone-500">Crediti e debiti</p>
                <h1 class="font-display text-2xl">Nuovo prestito</h1>
            </div>
        </template>

        <form class="panel mx-auto max-w-2xl space-y-4 p-6" @submit.prevent="submit">
            <p class="text-sm text-stone-500">
                Un prestito concesso sposta denaro dal conto a un credito. Il patrimonio non cambia. Gli interessi, se ci sono, si registrano al rimborso.
            </p>
            <div>
                <label class="label" for="direction">Tipo</label>
                <SelectField
                    id="direction"
                    v-model="form.direction"
                    :options="[
                        { value: 'given', label: 'Prestito concesso' },
                        { value: 'received', label: 'Prestito ricevuto' },
                    ]"
                />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="counterparty_id">Persona</label>
                    <SelectField
                        id="counterparty_id"
                        v-model="form.counterparty_id"
                        :options="[{ value: '', label: 'Nuova' }, ...counterparties.map((person) => ({ value: person.id, label: person.name }))]"
                    />
                </div>
                <div>
                    <label class="label" for="counterparty_name">Nome, se nuova</label>
                    <input id="counterparty_name" v-model="form.counterparty_name" class="field" placeholder="Marco" />
                    <InputError class="mt-2" :message="form.errors.counterparty_name" />
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="amount">Capitale</label>
                    <input id="amount" v-model="form.amount" class="field" placeholder="200,00" required />
                    <InputError class="mt-2" :message="form.errors.amount" />
                </div>
                <div>
                    <label class="label" for="account_id">Conto</label>
                    <SelectField
                        id="account_id"
                        v-model="form.account_id"
                        required
                        :options="[{ value: '', label: 'Seleziona' }, ...accounts.map((account) => ({ value: account.id, label: account.name }))]"
                    />
                    <InputError class="mt-2" :message="form.errors.account_id" />
                </div>
            </div>
            <div>
                <label class="label" for="description">Descrizione</label>
                <input id="description" v-model="form.description" class="field" required />
                <InputError class="mt-2" :message="form.errors.description" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="opened_on">Data</label>
                    <input id="opened_on" v-model="form.opened_on" type="date" class="field" required />
                </div>
                <div>
                    <label class="label" for="due_on">Scadenza</label>
                    <input id="due_on" v-model="form.due_on" type="date" class="field" />
                </div>
            </div>
            <div>
                <label class="label" for="agreed_interest">Interessi concordati</label>
                <input id="agreed_interest" v-model="form.agreed_interest" class="field" placeholder="Facoltativo, non viene addebitato da solo" />
            </div>
            <div>
                <label class="label" for="notes">Note</label>
                <textarea id="notes" v-model="form.notes" rows="3" class="field" />
            </div>
            <div class="flex justify-end">
                <button class="btn-primary" :disabled="form.processing">Registra</button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
