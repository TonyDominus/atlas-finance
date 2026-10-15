<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Atlas Finance" />

    <div class="min-h-screen bg-[#14211c] text-stone-100">
        <div class="mx-auto flex min-h-screen max-w-5xl flex-col justify-between px-6 py-8">
            <header class="flex items-center justify-between">
                <p class="font-display text-3xl">Atlas</p>
                <nav class="flex gap-3 text-sm">
                    <Link :href="route('login')" class="btn border border-white/15 bg-white/5">Entra</Link>
                    <Link :href="route('register')" class="btn bg-amber-100 text-stone-950 hover:bg-white">Crea account</Link>
                </nav>
            </header>

            <main class="max-w-2xl py-16">
                <p class="text-xs uppercase tracking-[0.22em] text-amber-200/80">Finanze personali</p>
                <h1 class="mt-4 font-display text-5xl leading-tight sm:text-6xl">Un libro conti privato, sul tuo server.</h1>
                <p class="mt-6 max-w-xl text-lg leading-8 text-stone-300">
                    Conti, spese, entrate e trasferimenti in partita doppia. Il saldo si ricostruisce dai movimenti. Nessun dato di esempio finché non lo chiedi.
                </p>
            </main>

            <p class="text-xs text-stone-400">EUR · Europe/Rome · self-hosted</p>
        </div>
    </div>
</template>
