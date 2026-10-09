<script setup lang="ts">
import { Head, Link as InertiaLink } from "@inertiajs/vue3";
import { computed } from "vue";
import Heading from "@/components/Heading.vue";
import { Button } from "@/components/ui/button";
import { index, stats } from "@/routes/links";
import type { DailyClicks, Link, StatsRow } from "@/types";

const props = defineProps<{
    link: Link;
    days: number;
    total: number;
    daily: DailyClicks[];
    countries: StatsRow[];
    cities: StatsRow[];
}>();

const ranges = [7, 30, 90];

const maxDaily = computed(() =>
    Math.max(1, ...props.daily.map((day) => day.count)),
);

defineOptions({
    layout: {
        breadcrumbs: [{ title: "Links", href: index() }],
    },
});
</script>

<template>
    <Head title="Link stats" />

    <div class="space-y-6 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <Heading
                :title="link.title ?? link.short_url"
                :description="link.short_url"
            />
            <div class="flex gap-2">
                <Button
                    v-for="range in ranges"
                    :key="range"
                    :variant="range === days ? 'default' : 'outline'"
                    size="sm"
                    as-child
                >
                    <InertiaLink
                        :href="stats(link.id, { query: { days: range } })"
                        preserve-scroll
                    >
                        {{ range }} days
                    </InertiaLink>
                </Button>
            </div>
        </div>

        <p class="text-3xl font-semibold">
            {{ total }}
            <span class="text-base font-normal text-muted-foreground"
                >clicks in the last {{ days }} days</span
            >
        </p>

        <p v-if="total === 0" class="text-sm text-muted-foreground">
            No clicks in this period yet.
        </p>

        <div
            v-else
            class="flex h-40 items-end gap-px"
            role="img"
            aria-label="Clicks per day"
        >
            <div
                v-for="day in daily"
                :key="day.date"
                class="min-w-px flex-1 rounded-t bg-primary"
                :style="{
                    height: `${(day.count / maxDaily) * 100}%`,
                    minHeight: day.count > 0 ? '2px' : '0',
                }"
                :title="`${day.date}: ${day.count}`"
            />
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <section
                v-for="table in [
                    { title: 'Countries', rows: countries },
                    { title: 'Cities', rows: cities },
                ]"
                :key="table.title"
            >
                <h2 class="mb-2 font-medium">{{ table.title }}</h2>
                <p
                    v-if="table.rows.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No data.
                </p>
                <table v-else class="w-full text-left text-sm">
                    <tbody>
                        <tr
                            v-for="row in table.rows"
                            :key="row.name"
                            class="border-t"
                        >
                            <td class="p-2">{{ row.name }}</td>
                            <td class="p-2 text-right">{{ row.count }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</template>
