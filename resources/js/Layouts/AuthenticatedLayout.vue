<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const open = ref(false);
const dark = ref(false);
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash);

const items = [
    { name: 'Dashboard', href: route('dashboard'), match: 'dashboard' },
    { name: 'Conti', href: route('accounts.index'), match: 'accounts.*' },
    { name: 'Movimenti', href: route('movements.index'), match: 'movements.*' },
    { name: 'Ricorrenze', href: route('recurrences.index'), match: 'recurrences.*' },
    { name: 'Calendario', href: route('calendar.index'), match: 'calendar.index' },
    { name: 'Prestiti', href: route('loans.index'), match: 'loans.*' },
    { name: 'Budget', href: route('budgets.index'), match: 'budgets.*' },
    { name: 'Analisi', href: route('reports.index'), match: 'reports.index' },
    { name: 'Controparti', href: route('counterparties.index'), match: 'counterparties.*' },
    { name: 'Categorie', href: route('categories.index'), match: 'categories.*' },
];

onMounted(() => {
    dark.value = document.documentElement.classList.contains('dark');
});

function toggleTheme(): void {
    dark.value = !dark.value;
    document.documentElement.classList.toggle('dark', dark.value);
    localStorage.setItem('atlas-theme', dark.value ? 'dark' : 'light');
}

function active(match: string): boolean {
    return route().current(match) === true;
}
</script>

<template>
    <div class="min-h-screen bg-[#f3efe6] text-stone-900 dark:bg-[#10130f] dark:text-stone-100">
        <div
            v-if="open"
            class="fixed inset-0 z-30 bg-stone-950/40 lg:hidden"
            @click="open = false"
        />

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col bg-[#14211c] text-stone-100 transition-transform lg:translate-x-0"
            :class="open ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="px-6 pb-4 pt-7">
                <Link :href="route('dashboard')" class="block" @click="open = false">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-amber-200/80">Personale</p>
                    <p class="font-display text-3xl leading-none text-white">Atlas</p>
                </Link>
            </div>

            <nav class="mt-4 flex-1 space-y-1 px-3">
                <Link
                    v-for="item in items"
                    :key="item.name"
                    :href="item.href"
                    class="block rounded-xl px-3 py-2.5 text-sm font-medium transition"
                    :class="active(item.match) ? 'bg-white/10 text-white' : 'text-stone-300 hover:bg-white/5 hover:text-white'"
                    @click="open = false"
                >
                    {{ item.name }}
                </Link>
            </nav>

            <div class="space-y-2 border-t border-white/10 p-4">
                <p class="truncate px-2 text-sm font-medium">{{ user.name }}</p>
                <div class="flex gap-2">
                    <Link :href="route('profile.edit')" class="btn flex-1 border border-white/10 bg-white/5 text-stone-100 hover:bg-white/10">
                        Profilo
                    </Link>
                    <Link :href="route('logout')" method="post" as="button" class="btn flex-1 border border-white/10 bg-white/5 text-stone-100 hover:bg-white/10">
                        Esci
                    </Link>
                </div>
            </div>
        </aside>

        <div class="lg:pl-72">
            <header class="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-stone-200/80 bg-[#f3efe6]/90 px-4 py-3 backdrop-blur dark:border-white/10 dark:bg-[#10130f]/90 sm:px-8">
                <button type="button" class="btn-secondary px-3 lg:hidden" @click="open = true">Menu</button>
                <div class="min-w-0 flex-1">
                    <slot name="header" />
                </div>
                <button type="button" class="btn-secondary" @click="toggleTheme">
                    {{ dark ? 'Chiaro' : 'Scuro' }}
                </button>
            </header>

            <main class="px-4 py-6 sm:px-8 sm:py-8">
                <div v-if="flash.success" class="mb-4 rounded-2xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900 dark:border-teal-900 dark:bg-teal-950 dark:text-teal-100">
                    {{ flash.success }}
                </div>
                <div v-if="flash.error" class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-100">
                    {{ flash.error }}
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
