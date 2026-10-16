<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SelectField from '@/Components/SelectField.vue';
import EmptyState from '@/Components/EmptyState.vue';
import InputError from '@/Components/InputError.vue';
import { CounterpartyOption, Option } from '@/types/finance';
import { Head, router, useForm } from '@inertiajs/vue3';

defineProps<{
    counterparties: CounterpartyOption[];
    types: Option[];
}>();

const form = useForm({
    name: '',
    type: 'person',
    notes: '',
});

function submit(): void {
    form.post(route('counterparties.store'), {
        onSuccess: () => form.reset('name', 'notes'),
    });
}

function remove(id: number): void {
    router.delete(route('counterparties.destroy', id));
}
</script>

<template>
    <Head title="Controparti" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs uppercase tracking-[0.16em] text-stone-500">Anagrafica</p>
                <h1 class="font-display text-2xl">Persone e controparti</h1>
            </div>
        </template>

        <div class="grid gap-6 xl:grid-cols-[0.8fr_1.2fr]">
            <form class="panel space-y-3 p-6" @submit.prevent="submit">
                <h2 class="font-display text-xl">Nuova</h2>
                <input v-model="form.name" class="field" placeholder="Nome" required />
                <InputError :message="form.errors.name" />
                <SelectField v-model="form.type" :options="types" />
                <textarea v-model="form.notes" rows="3" class="field" placeholder="Note" />
                <button class="btn-primary" :disabled="form.processing">Aggiungi</button>
            </form>

            <EmptyState
                v-if="counterparties.length === 0"
                title="Nessuna controparte"
                body="Persone, esercenti o istituti. Serviranno anche per i prestiti."
            />

            <div v-else class="panel overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-stone-50 text-left text-xs uppercase tracking-wider text-stone-500 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-3">Nome</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Movimenti</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="person in counterparties" :key="person.id" class="border-t border-stone-100 dark:border-white/10">
                            <td class="px-4 py-3 font-medium">{{ person.name }}</td>
                            <td class="px-4 py-3">{{ person.type_label }}</td>
                            <td class="px-4 py-3">{{ person.movements_count }}</td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    class="text-sm text-rose-800 dark:text-rose-300"
                                    @click="remove(person.id)"
                                >
                                    Elimina
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
