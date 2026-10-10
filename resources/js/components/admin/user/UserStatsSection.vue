<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import ClicksChart from '@/components/ClicksChart.vue';
import { Button } from '@/components/ui/button';
import { show } from '@/routes/admin/users';
import type { AdminCounters, DailyClicks, StatsPeriodParams } from '@/types';

const props = defineProps<{
    userId: string;
    period: StatsPeriodParams;
    granularity: 'day' | 'month';
    total: number;
    series: DailyClicks[];
    counters: AdminCounters;
}>();

const ranges = [7, 30, 90];

const cards = computed(() => [
    { label: 'Links', value: props.counters.links },
    {
        label: 'Active / disabled links',
        value: `${props.counters.activeLinks} / ${props.counters.disabledLinks}`,
    },
    { label: 'Clicks', value: props.counters.clicks },
]);

const setPeriod = (range: number) =>
    router.get(
        show(props.userId).url,
        { period: `${range}d` },
        { preserveScroll: true },
    );
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-medium">Statistics</h2>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-for="range in ranges"
                    :key="range"
                    :variant="
                        `${range}d` === period.period ? 'default' : 'outline'
                    "
                    size="sm"
                    :aria-pressed="`${range}d` === period.period"
                    @click="setPeriod(range)"
                >
                    {{ range }} days
                </Button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div
                v-for="card in cards"
                :key="card.label"
                class="rounded-lg border p-4"
            >
                <p class="text-sm text-muted-foreground">{{ card.label }}</p>
                <p class="text-2xl font-semibold tabular-nums">
                    {{ card.value }}
                </p>
            </div>
        </div>

        <p v-if="total === 0" class="text-sm text-muted-foreground">
            No clicks in this period yet.
        </p>
        <ClicksChart v-else :series="series" :granularity="granularity" />
    </div>
</template>
