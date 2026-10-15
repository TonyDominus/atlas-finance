<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { CategoryOption, Option } from '@/types/finance';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    categories: CategoryOption[];
    kinds: Option[];
}>();

const expense = computed(() => props.categories.filter((category) => category.kind === 'expense' && !category.parent_id));
const income = computed(() => props.categories.filter((category) => category.kind === 'income' && !category.parent_id));

function children(parentId: number): CategoryOption[] {
    return props.categories.filter((category) => category.parent_id === parentId);
}

const form = useForm({
    name: '',
    kind: 'expense',
    parent_id: '' as number | '',
});

const parents = computed(() => props.categories.filter((category) => category.kind === form.kind && !category.parent_id));

function submit(): void {
    form.post(route('categories.store'), {
        onSuccess: () => form.reset('name', 'parent_id'),
    });
}
</script>

<template>
    <Head title="Categorie" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs uppercase tracking-[0.16em] text-stone-500">Classificazione</p>
                <h1 class="font-display text-2xl">Categorie</h1>
            </div>
        </template>

        <div class="grid gap-6 xl:grid-cols-[0.8fr_1.2fr]">
            <form class="panel space-y-3 p-6" @submit.prevent="submit">
                <h2 class="font-display text-xl">Nuova categoria</h2>
                <input v-model="form.name" class="field" placeholder="Nome" required />
                <InputError :message="form.errors.name" />
                <select v-model="form.kind" class="field">
                    <option v-for="kind in kinds" :key="kind.value" :value="kind.value">{{ kind.label }}</option>
                </select>
                <select v-model="form.parent_id" class="field">
                    <option value="">Categoria principale</option>
                    <option v-for="parent in parents" :key="parent.id" :value="parent.id">Sotto {{ parent.name }}</option>
                </select>
                <InputError :message="form.errors.parent_id" />
                <button class="btn-primary" :disabled="form.processing">Aggiungi</button>
            </form>

            <div class="grid gap-4 md:grid-cols-2">
                <section class="panel p-5">
                    <h2 class="font-display text-xl">Spese</h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li v-for="category in expense" :key="category.id">
                            <p class="font-medium">{{ category.name }}</p>
                            <p v-for="child in children(category.id)" :key="child.id" class="pl-3 text-stone-500">{{ child.name }}</p>
                        </li>
                    </ul>
                </section>
                <section class="panel p-5">
                    <h2 class="font-display text-xl">Entrate</h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li v-for="category in income" :key="category.id" class="font-medium">{{ category.name }}</li>
                    </ul>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
