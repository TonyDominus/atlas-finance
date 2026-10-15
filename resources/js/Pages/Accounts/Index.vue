<script setup lang="ts">
import AccountGlyph from '@/Components/AccountGlyph.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { AccountCard } from '@/types/finance';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    accounts: AccountCard[];
}>();

const active = computed(() => props.accounts.filter((account) => account.status !== 'archived'));
const archived = computed(() => props.accounts.filter((account) => account.status === 'archived'));
</script>

<template>
    <Head title="Conti" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.16em] text-stone-500">Conti</p>
                    <h1 class="font-display text-2xl">I tuoi strumenti</h1>
                </div>
                <Link :href="route('accounts.create')" class="btn-primary">Nuovo conto</Link>
            </div>
        </template>

        <EmptyState
            v-if="accounts.length === 0"
            title="Aggiungi un conto"
            body="Banca, carta, wallet o contanti. Il saldo che vedi è la somma delle scritture, a partire dall'apertura."
        >
            <template #action>
                <Link :href="route('accounts.create')" class="btn-primary">Crea il primo conto</Link>
            </template>
        </EmptyState>

        <div v-else class="space-y-8">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="account in active"
                    :key="account.id"
                    :href="route('accounts.show', account.id)"
                    class="panel p-5 transition hover:-translate-y-0.5"
                >
                    <div class="flex items-start justify-between gap-3">
                        <AccountGlyph :name="account.icon" :color="account.color" />
                        <span class="rounded-full bg-stone-100 px-2 py-1 text-xs text-stone-600 dark:bg-white/10 dark:text-stone-300">
                            {{ account.status_label }}
                        </span>
                    </div>
                    <h2 class="mt-4 text-lg font-semibold">{{ account.name }}</h2>
                    <p class="text-sm text-stone-500">{{ account.institution || account.type_label }}</p>
                    <p class="mt-4 font-display text-3xl tabular-nums">{{ account.balance }}</p>
                    <p v-if="!account.include_in_net_worth" class="mt-2 text-xs text-amber-800 dark:text-amber-200">Escluso dal patrimonio</p>
                </Link>
            </div>

            <section v-if="archived.length">
                <h2 class="mb-3 text-sm font-medium uppercase tracking-wider text-stone-500">Archiviati</h2>
                <div class="grid gap-3 md:grid-cols-2">
                    <Link
                        v-for="account in archived"
                        :key="account.id"
                        :href="route('accounts.show', account.id)"
                        class="panel flex items-center justify-between p-4 opacity-70"
                    >
                        <span>{{ account.name }}</span>
                        <span class="tabular-nums">{{ account.balance }}</span>
                    </Link>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
