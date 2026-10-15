<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import { formatDate } from '@/utils/date';
import { AccountOption, CategoryOption, CounterpartyOption, MovementSummary, Option, Paginator } from '@/types/finance';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps<{
    movements: Paginator<MovementSummary>;
    filters: Record<string, string>;
    accounts: AccountOption[];
    categories: CategoryOption[];
    counterparties: CounterpartyOption[];
    types: Option[];
}>();

const filters = reactive({ ...props.filters });

function apply(): void {
    router.get(route('movements.index'), { ...filters }, { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Movimenti" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.16em] text-stone-500">Ledger</p>
                    <h1 class="font-display text-2xl">Movimenti</h1>
                </div>
                <Link :href="route('movements.create')" class="btn-primary">Nuovo movimento</Link>
            </div>
        </template>

        <form class="panel mb-4 grid gap-3 p-4 md:grid-cols-4" @submit.prevent="apply">
            <input v-model="filters.q" class="field" placeholder="Cerca descrizione" />
            <select v-model="filters.type" class="field">
                <option value="">Tutti i tipi</option>
                <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
            </select>
            <select v-model="filters.account_id" class="field">
                <option value="">Tutti i conti</option>
                <option v-for="account in accounts" :key="account.id" :value="String(account.id)">{{ account.name }}</option>
            </select>
            <select v-model="filters.voided" class="field">
                <option value="">Tutti gli stati</option>
                <option value="no">Contabilizzati</option>
                <option value="yes">Annullati</option>
            </select>
            <input v-model="filters.from" type="date" class="field" />
            <input v-model="filters.to" type="date" class="field" />
            <select v-model="filters.category_id" class="field">
                <option value="">Tutte le categorie</option>
                <option v-for="category in categories" :key="category.id" :value="String(category.id)">{{ category.name }}</option>
            </select>
            <button class="btn-secondary">Filtra</button>
        </form>

        <EmptyState
            v-if="movements.data.length === 0"
            title="Nessun movimento con questi filtri"
            body="Registra una spesa, un'entrata o un trasferimento. I trasferimenti tra i tuoi conti non contano come uscite."
        />

        <div v-else class="panel overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-stone-50 text-left text-xs uppercase tracking-wider text-stone-500 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-3">Data</th>
                            <th class="px-4 py-3">Descrizione</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Conti</th>
                            <th class="px-4 py-3 text-right">Importo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="movement in movements.data" :key="movement.id" class="border-t border-stone-100 dark:border-white/10">
                            <td class="px-4 py-3 whitespace-nowrap">{{ formatDate(movement.booked_on) }}</td>
                            <td class="px-4 py-3">
                                <Link :href="route('movements.show', movement.id)" class="font-medium" :class="{ 'line-through opacity-60': movement.voided }">
                                    {{ movement.description }}
                                </Link>
                                <p class="text-xs text-stone-500">
                                    <span v-if="movement.category">{{ movement.category }}</span>
                                    <span v-if="movement.counterparty"> · {{ movement.counterparty }}</span>
                                </p>
                            </td>
                            <td class="px-4 py-3">{{ movement.type_label }}</td>
                            <td class="px-4 py-3">{{ movement.accounts.join(' → ') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium">{{ movement.amount }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="border-t border-stone-100 px-4 py-3 dark:border-white/10">
                <Pagination :links="movements.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
