<script setup lang="ts">
import { ChartColumn, ChartLine, Table } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import ClicksChart from '@/components/ClicksChart.vue';
import ClicksLineChart from '@/components/ClicksLineChart.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { DailyClicks } from '@/types';

const props = defineProps<{
    series: DailyClicks[];
    granularity: 'day' | 'month';
    total: number;
}>();

const storageKey = 'clicks-card-view';
type View = 'table' | 'chart' | 'line';

const views: View[] = ['table', 'chart', 'line'];
const view = ref<View>('table');
const hideEmpty = ref(true);

onMounted(() => {
    try {
        const stored = localStorage.getItem(storageKey) as View | null;

        view.value =
            stored !== null && views.includes(stored) ? stored : 'table';
    } catch {
        // storage unavailable: keep the default view
    }
});

function setView(next: View): void {
    view.value = next;

    try {
        localStorage.setItem(storageKey, next);
    } catch {
        // storage unavailable: the choice just is not remembered
    }
}

const maxCount = computed(() =>
    Math.max(1, ...props.series.map((row) => row.count)),
);

const rows = computed(() =>
    [...props.series]
        .reverse()
        .filter((row) => !hideEmpty.value || row.count > 0),
);

function formatDate(date: string): string {
    const monthly = props.granularity === 'month';

    return new Date(
        `${date}${monthly ? '-01' : ''}T00:00:00Z`,
    ).toLocaleDateString(
        'en-GB',
        monthly
            ? { month: 'short', year: 'numeric', timeZone: 'UTC' }
            : {
                  day: 'numeric',
                  month: 'short',
                  year: 'numeric',
                  timeZone: 'UTC',
              },
    );
}
</script>

<template>
    <section class="overflow-hidden rounded-lg border">
        <div
            class="flex flex-wrap items-center justify-between gap-2 px-4 py-3"
        >
            <h2 class="font-medium">
                Clicks
                <span class="ml-1 text-muted-foreground tabular-nums">
                    {{ total }} total
                </span>
            </h2>
            <div class="flex items-center gap-3">
                <div v-if="view === 'table'" class="flex items-center gap-2">
                    <Checkbox
                        id="hide-empty-days"
                        :model-value="hideEmpty"
                        @update:model-value="hideEmpty = $event === true"
                    />
                    <Label for="hide-empty-days" class="text-sm">
                        Hide empty
                        {{ granularity === 'month' ? 'months' : 'days' }}
                    </Label>
                </div>
                <div class="flex items-center gap-1 rounded-lg border p-1">
                    <Button
                        type="button"
                        size="sm"
                        :variant="view === 'table' ? 'default' : 'ghost'"
                        :aria-pressed="view === 'table'"
                        aria-label="Table view"
                        @click="setView('table')"
                    >
                        <Table />
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        :variant="view === 'chart' ? 'default' : 'ghost'"
                        :aria-pressed="view === 'chart'"
                        aria-label="Bar chart view"
                        @click="setView('chart')"
                    >
                        <ChartColumn />
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        :variant="view === 'line' ? 'default' : 'ghost'"
                        :aria-pressed="view === 'line'"
                        aria-label="Line chart view"
                        @click="setView('line')"
                    >
                        <ChartLine />
                    </Button>
                </div>
            </div>
        </div>
        <p v-if="total === 0" class="px-4 pb-4 text-sm text-muted-foreground">
            No clicks in this period yet.
        </p>
        <div v-else-if="view !== 'table'" class="space-y-2 px-4 pb-4">
            <ClicksChart
                v-if="view === 'chart'"
                :series="series"
                :granularity="granularity"
            />
            <ClicksLineChart
                v-else
                :series="series"
                :granularity="granularity"
            />
            <div class="flex justify-between text-xs text-muted-foreground">
                <span>{{ formatDate(series[0].date) }}</span>
                <span>{{ formatDate(series[series.length - 1].date) }}</span>
            </div>
        </div>
        <div v-else class="max-h-80 overflow-y-auto">
            <table class="w-full text-left text-sm">
                <thead class="sticky top-0 bg-muted text-muted-foreground">
                    <tr>
                        <th class="p-3">
                            {{ granularity === 'month' ? 'Month' : 'Day' }}
                        </th>
                        <th class="w-full p-3"></th>
                        <th class="p-3 text-right">Clicks</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in rows"
                        :key="row.date"
                        class="border-t"
                        :class="{ 'text-muted-foreground': row.count === 0 }"
                    >
                        <td class="p-3 whitespace-nowrap">
                            {{ formatDate(row.date) }}
                        </td>
                        <td class="p-3">
                            <div class="h-2 rounded-full bg-muted">
                                <div
                                    class="h-full rounded-full bg-primary"
                                    :style="{
                                        width: `${(row.count / maxCount) * 100}%`,
                                    }"
                                />
                            </div>
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            {{ row.count }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
