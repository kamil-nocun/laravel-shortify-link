<script setup lang="ts">
import { computed } from 'vue';
import type { StatsTable } from '@/types';

const props = defineProps<{
    title: string;
    table: StatsTable;
    sort: string;
}>();

const emit = defineEmits<{
    sort: [value: string];
    page: [page: number];
    perPage: [perPage: number];
}>();

const perPageOptions = [10, 25, 50, 100];

const column = computed(() =>
    props.sort.startsWith('name') ? 'name' : 'count',
);
const direction = computed(() => (props.sort.endsWith('asc') ? 'asc' : 'desc'));

const arrow = (key: string) =>
    column.value === key ? (direction.value === 'asc' ? ' ↑' : ' ↓') : '';

const toggle = (key: 'name' | 'count') => {
    if (column.value === key) {
        emit('sort', `${key}_${direction.value === 'asc' ? 'desc' : 'asc'}`);

        return;
    }

    emit('sort', key === 'name' ? 'name_asc' : 'count_desc');
};
</script>

<template>
    <section>
        <h2 class="mb-2 font-medium">{{ title }}</h2>
        <p v-if="table.rows.length === 0" class="text-sm text-muted-foreground">
            No data.
        </p>
        <table v-else class="w-full text-left text-sm">
            <thead>
                <tr>
                    <th class="p-2">
                        <button type="button" @click="toggle('name')">
                            Name{{ arrow('name') }}
                        </button>
                    </th>
                    <th class="p-2 text-right">
                        <button type="button" @click="toggle('count')">
                            Clicks{{ arrow('count') }}
                        </button>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in table.rows" :key="row.name" class="border-t">
                    <td class="p-2">{{ row.name }}</td>
                    <td class="p-2 text-right">{{ row.count }}</td>
                </tr>
            </tbody>
        </table>
        <div class="mt-2 flex flex-wrap items-center gap-3 text-sm">
            <label class="flex items-center gap-1">
                Rows
                <select
                    class="rounded-md border bg-background px-2 py-1"
                    :value="table.perPage"
                    @change="
                        emit(
                            'perPage',
                            Number(($event.target as HTMLSelectElement).value),
                        )
                    "
                >
                    <option v-for="n in perPageOptions" :key="n" :value="n">
                        {{ n }}
                    </option>
                </select>
            </label>
            <template v-if="table.lastPage > 1">
                <button
                    type="button"
                    class="rounded-md border px-2 py-1 disabled:opacity-50"
                    :disabled="table.page <= 1"
                    @click="emit('page', table.page - 1)"
                >
                    Previous
                </button>
                <button
                    type="button"
                    class="rounded-md border px-2 py-1 disabled:opacity-50"
                    :disabled="table.page >= table.lastPage"
                    @click="emit('page', table.page + 1)"
                >
                    Next
                </button>
            </template>
            <span>{{
                `Page ${table.page} of ${table.lastPage} (${table.total} rows)`
            }}</span>
        </div>
    </section>
</template>
