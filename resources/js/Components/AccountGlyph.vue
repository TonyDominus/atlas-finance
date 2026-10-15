<script setup lang="ts">
defineProps<{
    name: string;
    color?: string;
}>();
</script>

<template>
    <span
        class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl text-white shadow-sm"
        :style="{ backgroundColor: color || '#115e59' }"
    >
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <template v-if="name === 'card'">
                <rect x="3" y="6" width="18" height="12" rx="2" />
                <path d="M3 10h18" />
            </template>
            <template v-else-if="name === 'wallet'">
                <path d="M4 8.5A2.5 2.5 0 0 1 6.5 6H19v12H6.5A2.5 2.5 0 0 1 4 15.5v-7Z" />
                <path d="M16 12h3" />
            </template>
            <template v-else-if="name === 'cash'">
                <rect x="3" y="6" width="18" height="12" rx="2" />
                <circle cx="12" cy="12" r="2.2" />
            </template>
            <template v-else-if="name === 'chart'">
                <path d="M4 19V5" />
                <path d="M4 19h16" />
                <path d="M8 15l3.5-4 2.5 2.5L19 8" />
            </template>
            <template v-else-if="name === 'piggy'">
                <path d="M6 11a5 5 0 0 1 9.3-2H18a3 3 0 0 1 0 6h-.7A5 5 0 0 1 8 16.5" />
                <path d="M8 16.5V19M14 16.8V19" />
            </template>
            <template v-else>
                <path d="M4 10h16" />
                <path d="M5 10V8l7-4 7 4v2" />
                <path d="M6 10v8h12v-8" />
                <path d="M10 18v-4h4v4" />
            </template>
        </svg>
    </span>
</template>
