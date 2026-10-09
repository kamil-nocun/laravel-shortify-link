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
            class="min-w-px flex-1 rounded-t bg-primary"
            :style="{
                height: `${(day.count / maxDaily) * 100}%`,
                minHeight: day.count > 0 ? '2px' : '0',
            }"
            :title="`${day.date}: ${day.count}`"
        />
    </div>
</template>
