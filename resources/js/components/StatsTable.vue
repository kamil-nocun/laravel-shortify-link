<script setup lang="ts">
import { ChartPie, Table } from '@lucide/vue';
import { computed, ref } from 'vue';
import BreakdownChart from '@/components/BreakdownChart.vue';
import { Button } from '@/components/ui/button';
import type { StatsTable } from '@/types';

const props = defineProps<{
    title: string;
    label: string;
    total: number;
    table: StatsTable;
    sort: string;
    loading?: boolean;
}>();

const emit = defineEmits<{
    sort: [value: string];
    page: [delta: number];
    perPage: [perPage: number];
}>();

const view = ref<'chart' | 'table'>('chart');

const perPageOptions = [10, 25, 50, 100];

const column = computed(() =>
    props.sort.startsWith('name') ? 'name' : 'count',
);
const direction = computed(() => (props.sort.endsWith('asc') ? 'asc' : 'desc'));

const arrow = (key: string) =>
    column.value === key ? (direction.value === 'asc' ? '↑' : '↓') : '↕';

const share = (count: number) =>
    props.total > 0 ? (count / props.total) * 100 : 0;

const ariaSort = (key: string) =>
    column.value === key
        ? direction.value === 'asc'
            ? 'ascending'
            : 'descending'
        : 'none';

const toggle = (key: 'name' | 'count') => {
    if (column.value === key) {
        emit('sort', `${key}_${direction.value === 'asc' ? 'desc' : 'asc'}`);

        return;
    }

    emit('sort', key === 'name' ? 'name_asc' : 'count_desc');
};
</script>

<template>
    <section
        class="overflow-hidden rounded-lg border transition-opacity"
        :class="{ 'animate-pulse opacity-50': loading }"
        :aria-busy="loading"
    >
        <div class="flex items-center justify-between gap-2 px-4 py-3">
            <div class="flex items-baseline gap-2">
                <h2 class="font-medium">{{ title }}</h2>
                <span class="text-sm text-muted-foreground tabular-nums"
                    >{{ table.total }} rows</span
                >
            </div>
            <div class="flex items-center gap-1 rounded-lg border p-1">
                <Button
                    type="button"
                    size="sm"
                    :variant="view === 'chart' ? 'default' : 'ghost'"
                    :aria-pressed="view === 'chart'"
                    :aria-label="`${title} chart view`"
                    @click="view = 'chart'"
                >
                    <ChartPie />
                </Button>
                <Button
                    type="button"
                    size="sm"
                    :variant="view === 'table' ? 'default' : 'ghost'"
                    :aria-pressed="view === 'table'"
                    :aria-label="`${title} table view`"
                    @click="view = 'table'"
                >
                    <Table />
                </Button>
            </div>
        </div>
        <p
            v-if="table.rows.length === 0"
            class="border-t p-6 text-center text-sm text-muted-foreground"
        >
            No data.
        </p>
        <div v-else-if="view === 'chart'" class="space-y-2 px-4 pb-4">
            <BreakdownChart
                type="doughnut"
                :items="
                    table.rows.map((row) => ({
                        label: row.name,
                        value: row.count,
                    }))
                "
            />
            <p
                v-if="table.total > table.rows.length"
                class="text-xs text-muted-foreground"
            >
                Showing {{ table.rows.length }} of {{ table.total }} rows on
                this page.
            </p>
        </div>
        <table v-else class="w-full text-left text-sm">
            <thead class="bg-muted/50 text-muted-foreground">
                <tr>
                    <th class="p-2 pl-4" :aria-sort="ariaSort('name')">
                        <button
                            type="button"
                            class="rounded px-1 outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
                            @click="toggle('name')"
                        >
                            {{ label }}
                            <span
                                :class="{ 'opacity-50': column !== 'name' }"
                                aria-hidden="true"
                                >{{ arrow('name') }}</span
                            >
                        </button>
                    </th>
                    <th class="p-2 text-right" :aria-sort="ariaSort('count')">
                        <button
                            type="button"
                            class="rounded px-1 outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
                            @click="toggle('count')"
                        >
                            Clicks
                            <span
                                :class="{ 'opacity-50': column !== 'count' }"
                                aria-hidden="true"
                                >{{ arrow('count') }}</span
                            >
                        </button>
                    </th>
                    <th class="p-2 pr-4 text-right font-medium">Share</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="row in table.rows"
                    :key="row.name"
                    class="border-t hover:bg-muted/30"
                >
                    <td
                        class="p-2 pl-4"
                        :class="{
                            'text-muted-foreground italic':
                                row.name === 'Unknown',
                        }"
                    >
                        {{ row.name }}
                    </td>
                    <td class="p-2 text-right tabular-nums">{{ row.count }}</td>
                    <td class="relative p-2 pr-4 text-right tabular-nums">
                        {{ share(row.count).toFixed(1) }}%
                        <span
                            class="absolute bottom-0 left-0 h-0.5 bg-primary/60"
                            :style="{ width: `${share(row.count)}%` }"
                            aria-hidden="true"
                        />
                    </td>
                </tr>
            </tbody>
        </table>
        <div
            class="flex flex-wrap items-center gap-3 border-t px-4 py-3 text-sm"
        >
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
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="table.page <= 1"
                    @click="emit('page', -1)"
                >
                    Previous
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="table.page >= table.lastPage"
                    @click="emit('page', 1)"
                >
                    Next
                </Button>
            </template>
            <span class="tabular-nums">{{
                `Page ${table.page} of ${table.lastPage} (${table.total} rows)`
            }}</span>
        </div>
    </section>
</template>
