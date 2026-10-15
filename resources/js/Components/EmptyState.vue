<script setup lang="ts">
defineProps<{
    title: string;
    body: string;
}>();
</script>

<template>
    <div class="panel px-6 py-12 text-center">
        <p class="font-display text-2xl text-stone-900 dark:text-stone-50">{{ title }}</p>
        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-stone-500 dark:text-stone-400">{{ body }}</p>
        <div v-if="$slots.action" class="mt-6">
            <slot name="action" />
        </div>
    </div>
</template>
