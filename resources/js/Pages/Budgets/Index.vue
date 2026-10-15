<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import InputError from '@/Components/InputError.vue';
import { CategoryOption } from '@/types/finance';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    month: string;
    budgets: {
        id: number;
        category: string;
        amount: string;
        used: string;
        remaining: string;
        percent: number | null;
        over: boolean;
        history: { month: string; label: string; used: string; over: boolean }[];
    }[];
    categories: CategoryOption[];
    history_sufficient: boolean;
    notes: string[];
}>();

const form = useForm({
    category_id: '' as number | '',
    amount: '',
});

function submit(): void {
    form.post(route('budgets.store'), { preserveScroll: true, onSuccess: () => form.reset('amount') });
}
</script>

<template>
    <Head title="Budget" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs uppercase tracking-[0.16em] text-stone-500">{{ month }}</p>
                <h1 class="font-display text-2xl">Budget</h1>
            </div>
        </template>

        <div class="grid gap-6 xl:grid-cols-[0.8fr_1.2fr]">
            <form class="panel space-y-3 p-6" @submit.prevent="submit">
                <h2 class="font-display text-xl">Budget mensile</h2>
                <select v-model="form.category_id" class="field" required>
                    <option value="">Categoria di spesa</option>
                    <option v-for="category in categories" :key="category.id" :value="category.id">
                        {{ category.parent_id ? '– ' : '' }}{{ category.name }}
                    </option>
                </select>
                <input v-model="form.amount" class="field" placeholder="120,00" required />
                <InputError :message="form.errors.category_id || form.errors.amount" />
                <button class="btn-primary" :disabled="form.processing">Salva</button>
            </form>

            <section class="panel p-6">
                <h2 class="font-display text-xl">Note sui dati</h2>
                <p v-if="!history_sufficient" class="mt-2 text-sm text-stone-500">
                    Lo storico non è sufficiente per un confronto sulle abitudini: servono movimenti nei tre mesi precedenti.
                </p>
                <ul class="mt-3 space-y-2 text-sm">
                    <li v-for="note in notes" :key="note">{{ note }}</li>
                </ul>
            </section>
        </div>

        <EmptyState
            v-if="budgets.length === 0"
            class="mt-6"
            title="Nessun budget"
            body="Assegna un tetto mensile a una categoria. L'utilizzo conta solo spese, commissioni e rimborsi di quella categoria."
        />

        <div v-else class="mt-6 grid gap-4 lg:grid-cols-2">
            <article v-for="budget in budgets" :key="budget.id" class="panel p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-medium">{{ budget.category }}</h2>
                        <p class="text-sm text-stone-500">{{ budget.used }} di {{ budget.amount }}</p>
                    </div>
                    <Link :href="route('budgets.destroy', budget.id)" method="delete" as="button" class="text-sm text-rose-800 dark:text-rose-300">Rimuovi</Link>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-stone-100 dark:bg-white/10">
                    <div
                        class="h-full rounded-full"
                        :class="budget.over ? 'bg-rose-700' : 'bg-teal-800'"
                        :style="{ width: Math.min(budget.percent ?? 0, 100) + '%' }"
                    />
                </div>
                <p class="mt-2 text-sm" :class="budget.over ? 'text-rose-800 dark:text-rose-300' : 'text-stone-600 dark:text-stone-300'">
                    Resta {{ budget.remaining }}
                    <span v-if="budget.percent !== null"> · {{ budget.percent }}%</span>
                    <span v-if="budget.over"> · superato</span>
                </p>
                <ul class="mt-3 grid grid-cols-3 gap-2 text-xs text-stone-500">
                    <li v-for="month in budget.history" :key="month.month">
                        <span class="block">{{ month.label }}</span>
                        <span class="tabular-nums" :class="month.over ? 'text-rose-700 dark:text-rose-300' : ''">{{ month.used }}</span>
                    </li>
                </ul>
            </article>
        </div>
    </AuthenticatedLayout>
</template>
