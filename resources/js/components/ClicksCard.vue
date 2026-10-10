<script setup lang="ts">
import { ChartColumn, ChartLine } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import ClicksTrendChart from '@/components/ClicksTrendChart.vue';
import { Button } from '@/components/ui/button';
import type { DailyClicks } from '@/types';

const props = defineProps<{
    series: DailyClicks[];
    granularity: 'day' | 'month';
    total: number;
}>();

type View = 'bar' | 'line';

const storageKey = 'clicks-card-view';
const view = ref<View>('line');

onMounted(() => {
    try {
        const stored = localStorage.getItem(storageKey);

        if (stored === 'bar' || stored === 'line') {
            view.value = stored;
        }
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
            <div class="flex items-center gap-1 rounded-lg border p-1">
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
                <Button
                    type="button"
                    size="sm"
                    :variant="view === 'bar' ? 'default' : 'ghost'"
                    :aria-pressed="view === 'bar'"
                    aria-label="Bar chart view"
                    @click="setView('bar')"
                >
                    <ChartColumn />
                </Button>
            </div>
        </div>
        <p v-if="total === 0" class="px-4 pb-4 text-sm text-muted-foreground">
            No clicks in this period yet.
        </p>
        <div v-else class="px-4 pb-4">
            <ClicksTrendChart
                :type="view"
                :series="series"
                :granularity="granularity"
                :format-date="formatDate"
            />
        </div>
    </section>
</template>
