<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    ArcElement,
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js';
import { computed } from 'vue';
import { Bar, Doughnut } from 'vue-chartjs';
import { useChartColors } from '@/composables/useChartColors';

ChartJS.register(
    ArcElement,
    BarElement,
    CategoryScale,
    Legend,
    LinearScale,
    Tooltip,
);

const props = defineProps<{
    type: 'doughnut' | 'bar';
    items: { label: string; value: number }[];
    urls?: string[];
    valueLabel?: string;
}>();

const colors = useChartColors();

const height = computed(() =>
    props.type === 'doughnut'
        ? '16rem'
        : `${Math.max(10, props.items.length * 2 + 3)}rem`,
);

const data = computed(() => ({
    labels: props.items.map((item) => item.label),
    datasets: [
        {
            label: props.valueLabel ?? 'Clicks',
            data: props.items.map((item) => item.value),
            backgroundColor:
                props.type === 'doughnut'
                    ? props.items.map(
                          (_, index) =>
                              colors.value.palette[
                                  index % colors.value.palette.length
                              ],
                      )
                    : colors.value.primary,
            borderWidth: props.type === 'doughnut' ? 0 : undefined,
            borderRadius: props.type === 'bar' ? 4 : undefined,
            maxBarThickness: 22,
        },
    ],
}));

function open(index: number | undefined): void {
    const url = index === undefined ? undefined : props.urls?.[index];

    if (url) {
        router.visit(url);
    }
}

const options = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: props.type === 'bar' ? ('y' as const) : undefined,
    onClick: (_event: unknown, elements: { index: number }[]) =>
        open(elements[0]?.index),
    onHover: (event: { native: Event | null }, elements: unknown[]) => {
        const target = event.native?.target as HTMLElement | undefined;

        if (target && props.urls) {
            target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
        }
    },
    plugins: {
        legend:
            props.type === 'doughnut'
                ? {
                      position: 'bottom' as const,
                      labels: { color: colors.value.muted, boxWidth: 12 },
                  }
                : { display: false },
    },
    ...(props.type === 'bar'
        ? {
              scales: {
                  x: {
                      beginAtZero: true,
                      ticks: { color: colors.value.muted, precision: 0 },
                      grid: { color: colors.value.border },
                  },
                  y: {
                      ticks: { color: colors.value.muted },
                      grid: { display: false },
                  },
              },
          }
        : { cutout: '60%' }),
}));
</script>

<template>
    <div :style="{ height }">
        <Doughnut v-if="type === 'doughnut'" :data="data" :options="options" />
        <Bar v-else :data="data" :options="options" />
    </div>
</template>
