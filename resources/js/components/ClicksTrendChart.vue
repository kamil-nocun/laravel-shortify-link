<script setup lang="ts">
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Filler,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';
import { computed } from 'vue';
import { Bar, Line } from 'vue-chartjs';
import { useChartColors } from '@/composables/useChartColors';
import type { DailyClicks } from '@/types';

ChartJS.register(
    BarElement,
    CategoryScale,
    Filler,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
);

const props = defineProps<{
    type: 'bar' | 'line';
    series: DailyClicks[];
    granularity: 'day' | 'month';
    formatDate: (date: string) => string;
}>();

const colors = useChartColors();

const data = computed(() => ({
    labels: props.series.map((row) => props.formatDate(row.date)),
    datasets: [
        {
            label: 'Clicks',
            data: props.series.map((row) => row.count),
            borderColor: colors.value.primary,
            backgroundColor:
                props.type === 'line'
                    ? `color-mix(in srgb, ${colors.value.primary} 15%, transparent)`
                    : colors.value.primary,
            fill: true,
            tension: 0.3,
            pointRadius: props.series.length > 31 ? 0 : 3,
            maxBarThickness: 24,
            borderRadius: 4,
        },
    ],
}));

const options = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index' as const, intersect: false },
    plugins: { legend: { display: false } },
    scales: {
        x: {
            ticks: { color: colors.value.muted, maxTicksLimit: 8 },
            grid: { display: false },
        },
        y: {
            beginAtZero: true,
            ticks: { color: colors.value.muted, precision: 0 },
            grid: { color: colors.value.border },
        },
    },
}));
</script>

<template>
    <div class="h-64">
        <Bar v-if="type === 'bar'" :data="data" :options="options" />
        <Line v-else :data="data" :options="options" />
    </div>
</template>
