<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { formatDate } from '@/utils/date';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    summary: {
        currencies: {
            currency: string;
            given: string;
            recovered: string;
            residual: string;
            debt: string;
            overdue: number;
        }[];
    };
    loans: {
        id: number;
        direction_label: string;
        description: string;
        counterparty: string;
        opened_on: string;
        due_on: string | null;
        disbursed: string;
        residual: string;
        status_label: string;
        overdue: boolean;
    }[];
}>();
</script>

<template>
    <Head title="Prestiti" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.16em] text-stone-500">Crediti e debiti</p>
                    <h1 class="font-display text-2xl">Prestiti</h1>
                </div>
                <Link :href="route('loans.create')" class="btn-primary">Nuovo prestito</Link>
            </div>
        </template>

        <div v-if="summary.currencies.length" class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article v-for="row in summary.currencies" :key="row.currency" class="contents">
                <div class="panel p-4">
                    <p class="text-xs uppercase tracking-wider text-stone-500">Prestato</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">{{ row.given }}</p>
                </div>
                <div class="panel p-4">
                    <p class="text-xs uppercase tracking-wider text-stone-500">Recuperato</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">{{ row.recovered }}</p>
                </div>
                <div class="panel p-4">
                    <p class="text-xs uppercase tracking-wider text-stone-500">Ancora da recuperare</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">{{ row.residual }}</p>
                    <p v-if="row.overdue" class="mt-1 text-xs text-rose-700 dark:text-rose-300">{{ row.overdue }} scaduti</p>
                </div>
                <div class="panel p-4">
                    <p class="text-xs uppercase tracking-wider text-stone-500">Debiti verso terzi</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">{{ row.debt }}</p>
                </div>
            </article>
        </div>

        <EmptyState
            v-if="loans.length === 0"
            title="Nessun prestito"
            body="Prestare denaro riduce la liquidità e aumenta un credito. Non è una spesa, e la restituzione del capitale non è un'entrata."
        >
            <template #action>
                <Link :href="route('loans.create')" class="btn-primary">Registra un prestito</Link>
            </template>
        </EmptyState>

        <ul v-else class="space-y-3">
            <li v-for="loan in loans" :key="loan.id">
                <Link :href="route('loans.show', loan.id)" class="panel flex flex-wrap items-center justify-between gap-3 p-5">
                    <div>
                        <p class="font-medium">{{ loan.counterparty }}</p>
                        <p class="text-sm text-stone-500">
                            {{ loan.direction_label }} · {{ loan.description }} · {{ formatDate(loan.opened_on) }}
                            <span v-if="loan.due_on"> · scadenza {{ formatDate(loan.due_on) }}</span>
                        </p>
                        <p class="mt-1 text-xs" :class="loan.overdue ? 'text-rose-700 dark:text-rose-300' : 'text-stone-500'">
                            {{ loan.status_label }}<span v-if="loan.overdue"> · scaduto</span>
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-stone-500">Residuo</p>
                        <p class="text-lg font-semibold tabular-nums">{{ loan.residual }}</p>
                        <p class="text-xs text-stone-500">su {{ loan.disbursed }}</p>
                    </div>
                </Link>
            </li>
        </ul>
    </AuthenticatedLayout>
</template>
