<script setup lang="ts">
import { computed } from 'vue';
import type { DailyClicks } from '@/types';

const props = defineProps<{
    series: DailyClicks[];
    granularity: 'day' | 'month';
}>();

const width = 100;
const height = 40;

const maxCount = computed(() =>
    Math.max(1, ...props.series.map((day) => day.count)),
);

const points = computed(() =>
    props.series.map((day, index) => ({
        ...day,
        x:
            props.series.length === 1
                ? width / 2
                : (index / (props.series.length - 1)) * width,
        y: height - (day.count / maxCount.value) * height,
    })),
);

const line = computed(() =>
    points.value.map((point) => `${point.x},${point.y}`).join(' '),
);

const area = computed(() => `0,${height} ${line.value} ${width},${height}`);
</script>

<template>
    <div class="flex gap-2">
        <div
            class="flex h-40 flex-col justify-between text-xs text-muted-foreground tabular-nums"
        >
            <span>{{ maxCount }}</span>
            <span>0</span>
        </div>
        <svg
            class="h-40 w-full text-primary"
            :viewBox="`0 0 ${width} ${height}`"
            preserveAspectRatio="none"
            role="img"
            :aria-label="`Clicks per ${granularity}`"
        >
            <line
                x1="0"
                :y1="height"
                :x2="width"
                :y2="height"
                class="stroke-border"
                stroke-width="1"
                vector-effect="non-scaling-stroke"
            />
            <polygon :points="area" fill="currentColor" fill-opacity="0.12" />
            <polyline
                :points="line"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linejoin="round"
                vector-effect="non-scaling-stroke"
            />
            <rect
                v-for="point in points"
                :key="point.date"
                :x="point.x - width / points.length / 2"
                y="0"
                :width="width / points.length"
                :height="height"
                fill="transparent"
            >
                <title>{{ point.date }}: {{ point.count }}</title>
            </rect>
        </svg>
    </div>
</template>
