<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { formatDate } from '@/utils/date';
import { CategoryOption, CounterpartyOption, MovementDetail } from '@/types/finance';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    movement: MovementDetail;
    categories: CategoryOption[];
    counterparties: CounterpartyOption[];
}>();

const kind = computed(() => (props.movement.type === 'income' ? 'income' : 'expense'));
const categories = computed(() => props.categories.filter((category) => category.kind === kind.value));

const details = useForm({
    description: props.movement.description,
    notes: props.movement.notes ?? '',
    category_id: props.movement.category_id ?? ('' as number | ''),
    counterparty_id: props.movement.counterparty_id ?? ('' as number | ''),
    tags: props.movement.tags.join(', '),
});

const voidForm = useForm({
    reason: '',
});

const posting = useForm({});

function save(): void {
    details.patch(route('movements.update', props.movement.id));
}

function cancel(): void {
    voidForm.post(route('movements.void', props.movement.id));
}

function postForecast(): void {
    posting.post(route('movements.post', props.movement.id));
}
</script>

<template>
    <Head :title="movement.description" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs uppercase tracking-[0.16em] text-stone-500">{{ movement.type_label }} · {{ movement.origin }}</p>
                <h1 class="font-display text-2xl" :class="{ 'line-through opacity-60': movement.voided }">{{ movement.description }}</h1>
            </div>
        </template>

        <div class="grid gap-6 xl:grid-cols-[1fr_0.8fr]">
            <section class="panel p-6">
                <p class="font-display text-4xl tabular-nums">{{ movement.amount }}</p>
                <p class="mt-2 text-sm text-stone-500">
                    Operazione {{ formatDate(movement.occurred_on) }} · contabile {{ formatDate(movement.booked_on) }}
                </p>
                <p v-if="movement.status === 'forecast'" class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:bg-amber-950 dark:text-amber-100">
                    Previsione: il saldo non la include finché non la contabilizzi.
                </p>
                <form v-if="movement.status === 'forecast' && !movement.voided" class="mt-4" @submit.prevent="postForecast">
                    <button class="btn-primary" :disabled="posting.processing">Contabilizza ora</button>
                </form>
                <p v-if="movement.voided" class="mt-3 text-sm text-rose-800 dark:text-rose-200">
                    Annullato. {{ movement.void_reason }}
                </p>
                <p v-if="movement.accounts.length" class="mt-3 text-sm">Conti: {{ movement.accounts.join(' → ') }}</p>
                <p v-if="movement.external_id" class="mt-2 text-xs text-stone-500">ID esterno {{ movement.external_id }}</p>

                <h2 class="mt-6 text-sm font-semibold uppercase tracking-wider text-stone-500">Scritture</h2>
                <table class="mt-2 w-full text-sm">
                    <tbody>
                        <tr v-for="(line, index) in movement.lines" :key="index" class="border-t border-stone-100 dark:border-white/10">
                            <td class="py-2">{{ line.account }}</td>
                            <td class="py-2">{{ line.side }}</td>
                            <td class="py-2 text-right tabular-nums">{{ line.amount }}</td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="movement.related.length" class="mt-4 text-sm">
                    <p class="font-medium">Collegati</p>
                    <Link v-for="related in movement.related" :key="related.id" :href="route('movements.show', related.id)" class="mt-1 block text-teal-800 dark:text-teal-300">
                        {{ related.description }}
                    </Link>
                </div>

                <ul v-if="movement.attachments.length" class="mt-4 space-y-1 text-sm">
                    <li v-for="file in movement.attachments" :key="file.id">
                        <a :href="route('attachments.show', file.id)" class="text-teal-800 underline dark:text-teal-300">{{ file.name }}</a>
                    </li>
                </ul>
            </section>

            <div class="space-y-4">
                <form v-if="movement.can_edit" class="panel space-y-3 p-6" @submit.prevent="save">
                    <h2 class="font-display text-xl">Dettagli</h2>
                    <p class="text-xs text-stone-500">Importo, conti e date non si riscrivono. Per correggerli annulla e registra di nuovo.</p>
                    <input v-model="details.description" class="field" required />
                    <select v-if="!['transfer', 'investment_buy', 'investment_sell', 'opening_balance'].includes(movement.type)" v-model="details.category_id" class="field">
                        <option value="">Senza categoria</option>
                        <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                    </select>
                    <select v-model="details.counterparty_id" class="field">
                        <option value="">Nessuna controparte</option>
                        <option v-for="person in counterparties" :key="person.id" :value="person.id">{{ person.name }}</option>
                    </select>
                    <input v-model="details.tags" class="field" placeholder="Tag separati da virgola" />
                    <textarea v-model="details.notes" rows="3" class="field" />
                    <InputError :message="details.errors.description || details.errors.category_id" />
                    <button class="btn-secondary" :disabled="details.processing">Salva dettagli</button>
                </form>

                <form v-if="movement.can_void" class="panel space-y-3 p-6" @submit.prevent="cancel">
                    <h2 class="font-display text-xl">Annulla</h2>
                    <p class="text-xs text-stone-500">
                        {{ movement.status === 'forecast' ? 'Chiude la previsione senza storno: non ha mai mosso i saldi.' : 'Crea uno storno opposto. Il movimento originale resta nello storico.' }}
                    </p>
                    <textarea v-model="voidForm.reason" rows="3" class="field" placeholder="Motivo" required />
                    <InputError :message="voidForm.errors.reason" />
                    <button class="btn-danger" :disabled="voidForm.processing">Annulla movimento</button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
