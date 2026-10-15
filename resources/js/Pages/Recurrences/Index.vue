<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { formatDate } from '@/utils/date';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps<{
    rules: {
        id: number;
        description: string;
        kind_label: string;
        amount: string;
        amount_is_estimate: boolean;
        account: string;
        frequency_label: string;
        interval: number;
        status: string;
        status_label: string;
        next_on: string | null;
        monthly: string;
    }[];
    monthly: number;
}>();

function status(id: number, value: string): void {
    router.post(route('recurrences.status', id), { status: value }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Ricorrenze" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.16em] text-stone-500">Previsioni</p>
                    <h1 class="font-display text-2xl">Ricorrenze</h1>
                </div>
                <Link :href="route('recurrences.create')" class="btn-primary">Nuova</Link>
            </div>
        </template>

        <p class="mb-4 max-w-3xl text-sm text-stone-600 dark:text-stone-300">
            Le scadenze sono stime. Una ricorrenza non diventa un movimento pagato solo perché è arrivato il giorno.
        </p>

        <EmptyState
            v-if="rules.length === 0"
            title="Nessuna ricorrenza"
            body="Aggiungi mutuo, affitto, abbonamenti o un pagamento una tantum con numero massimo di occorrenze uguale a 1."
        >
            <template #action>
                <Link :href="route('recurrences.create')" class="btn-primary">Crea la prima</Link>
            </template>
        </EmptyState>

        <ul v-else class="space-y-3">
            <li v-for="rule in rules" :key="rule.id" class="panel p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-medium">{{ rule.description }}</p>
                        <p class="mt-1 text-sm text-stone-500">
                            {{ rule.kind_label }} · {{ rule.account }} · {{ rule.frequency_label }}
                            <span v-if="rule.interval > 1"> ogni {{ rule.interval }}</span>
                            <span v-if="rule.amount_is_estimate"> · importo stimato</span>
                        </p>
                        <p class="mt-1 text-xs text-stone-500">
                            {{ rule.status_label }}
                            <span v-if="rule.next_on"> · prossima {{ formatDate(rule.next_on) }}</span>
                            <span v-else> · nessuna scadenza aperta</span>
                        </p>
                    </div>
                    <p class="text-lg font-semibold tabular-nums">{{ rule.amount }}</p>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <Link :href="route('recurrences.edit', rule.id)" class="btn-secondary">Modifica</Link>
                    <button v-if="rule.status === 'active'" type="button" class="btn-secondary" @click="status(rule.id, 'paused')">Sospendi</button>
                    <button v-if="rule.status === 'paused'" type="button" class="btn-secondary" @click="status(rule.id, 'active')">Riattiva</button>
                    <button v-if="rule.status !== 'ended'" type="button" class="btn-secondary" @click="status(rule.id, 'ended')">Termina</button>
                    <Link :href="route('recurrences.destroy', rule.id)" method="delete" as="button" class="btn-danger">Elimina</Link>
                </div>
            </li>
        </ul>
    </AuthenticatedLayout>
</template>
