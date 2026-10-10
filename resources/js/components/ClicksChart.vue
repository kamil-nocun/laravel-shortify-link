<script setup lang="ts">
import { computed } from 'vue';
import type { DailyClicks } from '@/types';

const props = defineProps<{
    series: DailyClicks[];
    granularity: 'day' | 'month';
    loading?: boolean;
}>();

const maxDaily = computed(() =>
    Math.max(1, ...props.series.map((day) => day.count)),
);
</script>

<template>
    <div
        class="flex h-40 items-end gap-px transition-opacity"
        :class="{ 'animate-pulse opacity-50': loading }"
        role="img"
        :aria-label="`Clicks per ${granularity}`"
    >
        <div
            v-for="day in series"
            :key="day.date"
            class="flex h-full min-w-px flex-1 items-end justify-center"
            :title="`${day.date}: ${day.count}`"
        >
            <div
                class="w-full max-w-6 rounded-t"
                :class="day.count > 0 ? 'bg-primary' : 'bg-muted'"
                :style="{
                    height: `${(day.count / maxDaily) * 100}%`,
                    minHeight: '2px',
                }"
            />
        </div>
    </div>
</template>
