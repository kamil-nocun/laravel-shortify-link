<script setup lang="ts">
import { computed, ref } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { DailyClicks } from '@/types';

const props = defineProps<{
    series: DailyClicks[];
    granularity: 'day' | 'month';
    total: number;
}>();

const hideEmpty = ref(true);

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
            <div class="flex items-center gap-2">
                <Checkbox
                    id="hide-empty-days"
                    :model-value="hideEmpty"
                    @update:model-value="hideEmpty = $event === true"
                />
                <Label for="hide-empty-days" class="text-sm">
                    Hide empty {{ granularity === 'month' ? 'months' : 'days' }}
                </Label>
            </div>
        </div>
        <p v-if="total === 0" class="px-4 pb-4 text-sm text-muted-foreground">
            No clicks in this period yet.
        </p>
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
