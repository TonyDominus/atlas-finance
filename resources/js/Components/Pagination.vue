<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    links: { url: string | null; label: string; active: boolean }[];
}>();
</script>

<template>
    <nav v-if="links.length > 3" class="flex flex-wrap gap-1">
        <component
            :is="link.url ? Link : 'span'"
            v-for="(link, index) in links"
            :key="index"
            :href="link.url || undefined"
            class="rounded-lg px-3 py-1.5 text-sm"
            :class="
                link.active
                    ? 'bg-teal-900 text-white dark:bg-teal-700'
                    : 'text-stone-600 dark:text-stone-300'
            "
            v-html="link.label"
        />
    </nav>
</template>
