<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-[#f3efe6] px-4 py-10 dark:bg-[#10130f]">
        <Link href="/" class="mb-6 text-center">
            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-teal-900 dark:text-amber-200">Atlas</p>
            <p class="font-display text-4xl text-stone-900 dark:text-stone-50">Finance</p>
        </Link>

        <div class="w-full max-w-md rounded-3xl border border-stone-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-stone-900">
            <slot />
        </div>
    </div>
</template>
