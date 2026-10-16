<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SelectField from '@/Components/SelectField.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { formatDate } from '@/utils/date';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    items: {
        id: number;
        due_on: string;
        description: string;
        kind_label: string;
        account: string;
        amount: string;
        estimate: boolean;
        status: string;
        status_label: string;
        movement_id: number | null;
        overdue: boolean;
        reminder: boolean;
        can_settle: boolean;
    }[];
    linkable: { id: number; description: string; booked_on: string; amount: string }[];
    from: string;
    until: string;
}>();

const groups = computed(() => {
    const map = new Map<string, typeof props.items>();

    props.items.forEach((item) => {
        const key = item.due_on.slice(0, 7);
        map.set(key, [...(map.get(key) ?? []), item]);
    });

    return [...map.entries()].map(([month, rows]) => ({ month, rows }));
});

function formFor(id: number) {
    return useForm({
        journal_entry_id: '' as number | '',
        amount: '',
    });
}

const forms = Object.fromEntries(props.items.map((item) => [item.id, formFor(item.id)]));
</script>

<template>
    <Head title="Calendario" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs uppercase tracking-[0.16em] text-stone-500">{{ formatDate(from) }} – {{ formatDate(until) }}</p>
                <h1 class="font-display text-2xl">Calendario finanziario</h1>
            </div>
        </template>

        <p class="mb-4 text-sm text-stone-600 dark:text-stone-300">
            Le righe previste non sono movimenti. Collegane uno esistente oppure contabilizzale quando il denaro si muove davvero.
        </p>

        <EmptyState
            v-if="items.length === 0"
            title="Nessuna scadenza in questo periodo"
            body="Le ricorrenze attive compariranno qui come previsioni, distinte dai movimenti già nel ledger."
        />

        <div v-else class="space-y-6">
            <section v-for="group in groups" :key="group.month" class="panel p-5">
                <h2 class="font-display text-xl">{{ group.month }}</h2>
                <ul class="mt-3 divide-y divide-stone-100 dark:divide-white/10">
                    <li v-for="item in group.rows" :key="item.id" class="py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">{{ item.description }}</p>
                                <p class="text-sm text-stone-500">
                                    {{ formatDate(item.due_on) }} · {{ item.kind_label }} · {{ item.account }}
                                    <span v-if="item.estimate"> · stima</span>
                                </p>
                                <p class="mt-1 text-xs" :class="item.overdue ? 'text-rose-700 dark:text-rose-300' : 'text-stone-500'">
                                    {{ item.status_label }}
                                    <span v-if="item.overdue"> · scaduta e non contabilizzata</span>
                                    <span v-if="item.reminder"> · promemoria</span>
                                </p>
                            </div>
                            <p class="font-semibold tabular-nums">{{ item.amount }}</p>
                        </div>
                        <div v-if="item.movement_id" class="mt-2">
                            <Link :href="route('movements.show', item.movement_id)" class="text-sm text-teal-800 dark:text-teal-300">Apri movimento</Link>
                        </div>
                        <div v-else-if="item.can_settle" class="mt-3 flex flex-wrap items-end gap-2">
                            <Link :href="route('occurrences.realize', item.id)" method="post" as="button" class="btn-primary">Contabilizza</Link>
                            <Link :href="route('occurrences.skip', item.id)" method="post" as="button" class="btn-secondary">Salta</Link>
                            <form class="flex flex-wrap items-center gap-2" @submit.prevent="forms[item.id].post(route('occurrences.link', item.id))">
                                <SelectField
                                    v-model="forms[item.id].journal_entry_id"
                                    flush
                                    class="w-56"
                                    :options="[{ value: '', label: 'Collega un movimento' }, ...linkable.map((movement) => ({ value: movement.id, label: `${movement.description} · ${movement.amount}` }))]"
                                />
                                <button class="btn-secondary" :disabled="forms[item.id].processing">Collega</button>
                            </form>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
