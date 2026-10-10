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
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Bar, Line } from 'vue-chartjs';
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

const colors = ref({ primary: '#888', muted: '#888', border: '#8884' });
let observer: MutationObserver | null = null;

function readColors(): void {
    const style = getComputedStyle(document.documentElement);
    const read = (name: string, fallback: string) =>
        style.getPropertyValue(name).trim() || fallback;

    colors.value = {
        primary: read('--primary', '#888'),
        muted: read('--muted-foreground', '#888'),
        border: read('--border', '#8884'),
    };
}

onMounted(() => {
    readColors();
    observer = new MutationObserver(readColors);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
});

onBeforeUnmount(() => observer?.disconnect());

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
