<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

defineOptions({ inheritAttrs: false });

export interface SelectOption {
    value: string | number;
    label: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: string | number | null;
        options: SelectOption[];
        id?: string;
        disabled?: boolean;
        required?: boolean;
        placeholder?: string;
        flush?: boolean;
    }>(),
    {
        disabled: false,
        required: false,
        placeholder: 'Seleziona',
        flush: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string | number];
}>();

const open = ref(false);
const root = ref<HTMLElement | null>(null);
const list = ref<HTMLElement | null>(null);
const active = ref(0);

const selectedIndex = computed(() =>
    props.options.findIndex((option) => String(option.value) === String(props.modelValue ?? '')),
);
const selected = computed(() => (selectedIndex.value >= 0 ? props.options[selectedIndex.value] : null));

function openMenu(): void {
    if (props.disabled || props.options.length === 0) {
        return;
    }

    open.value = true;
    active.value = selectedIndex.value >= 0 ? selectedIndex.value : 0;
}

function close(): void {
    open.value = false;
}

function toggle(): void {
    if (open.value) {
        close();
        return;
    }

    openMenu();
}

function choose(index: number): void {
    const option = props.options[index];

    if (!option) {
        return;
    }

    emit('update:modelValue', option.value);
    close();
}

function onPointer(event: MouseEvent): void {
    if (!root.value?.contains(event.target as Node)) {
        close();
    }
}

function onKey(event: KeyboardEvent): void {
    if (props.disabled) {
        return;
    }

    if (!open.value) {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp' || event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            openMenu();
        }

        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        close();
    } else if (event.key === 'ArrowDown') {
        event.preventDefault();
        active.value = Math.min(props.options.length - 1, active.value + 1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        active.value = Math.max(0, active.value - 1);
    } else if (event.key === 'Home') {
        event.preventDefault();
        active.value = 0;
    } else if (event.key === 'End') {
        event.preventDefault();
        active.value = Math.max(0, props.options.length - 1);
    } else if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        choose(active.value);
    } else if (event.key.length === 1 && /\S/.test(event.key)) {
        const letter = event.key.toLocaleLowerCase();
        const after = props.options.findIndex(
            (option, index) => index > active.value && option.label.toLocaleLowerCase().startsWith(letter),
        );
        const found = after >= 0
            ? after
            : props.options.findIndex((option) => option.label.toLocaleLowerCase().startsWith(letter));

        if (found >= 0) {
            active.value = found;
        }
    }
}

function scrollActive(): void {
    nextTick(() => {
        list.value?.querySelector<HTMLElement>('[data-active="true"]')?.scrollIntoView({ block: 'nearest' });
    });
}

watch(open, (isOpen) => {
    if (isOpen) {
        scrollActive();
    }
});

watch(active, () => {
    if (open.value) {
        scrollActive();
    }
});

onMounted(() => document.addEventListener('mousedown', onPointer));
onBeforeUnmount(() => document.removeEventListener('mousedown', onPointer));
</script>

<template>
    <div ref="root" class="relative" :class="[$attrs.class, open ? 'z-50' : '']">
        <button
            :id="id"
            type="button"
            class="field flex w-full items-center justify-between gap-3 border px-3 py-2 text-left"
            :class="[flush ? '!mt-0' : '', disabled ? 'cursor-not-allowed opacity-60' : '']"
            :disabled="disabled"
            :aria-expanded="open"
            aria-haspopup="listbox"
            @click="toggle"
            @keydown="onKey"
        >
            <span class="truncate" :class="selected ? '' : 'text-stone-400'">{{ selected?.label ?? placeholder }}</span>
            <svg class="h-4 w-4 shrink-0 text-stone-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path
                    fill-rule="evenodd"
                    d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                    clip-rule="evenodd"
                />
            </svg>
        </button>
        <input
            v-if="required"
            class="pointer-events-none absolute h-px w-px opacity-0"
            tabindex="-1"
            aria-hidden="true"
            :value="modelValue === '' || modelValue === null || modelValue === undefined ? '' : String(modelValue)"
            required
        />
        <ul
            v-if="open"
            ref="list"
            role="listbox"
            class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-xl border border-stone-200 bg-white py-1 shadow-lg dark:border-white/10 dark:bg-stone-950"
        >
            <li v-for="(option, index) in options" :key="`${index}-${option.value}`">
                <button
                    type="button"
                    role="option"
                    class="flex w-full px-3 py-2 text-left text-sm"
                    :class="
                        index === active
                            ? 'bg-teal-900 text-white'
                            : 'text-stone-800 hover:bg-stone-100 dark:text-stone-100 dark:hover:bg-white/10'
                    "
                    :data-active="index === active ? 'true' : undefined"
                    :aria-selected="String(option.value) === String(modelValue ?? '')"
                    @mouseenter="active = index"
                    @click="choose(index)"
                >
                    {{ option.label }}
                </button>
            </li>
        </ul>
    </div>
</template>
