<script setup lang="ts">
import { Head, Link as InertiaLink, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { index, stats } from '@/routes/links';
import type {
    DailyClicks,
    Link,
    StatsFilterKey,
    StatsFilters,
    StatsOptions,
    StatsPeriodParams,
    StatsRow,
} from '@/types';

const props = defineProps<{
    link: Link;
    period: StatsPeriodParams;
    granularity: 'day' | 'month';
    total: number;
    series: DailyClicks[];
    countries: StatsRow[];
    cities: StatsRow[];
    browsers: StatsRow[];
    systems: StatsRow[];
    devices: StatsRow[];
    filters: StatsFilters;
    options: StatsOptions;
}>();

const ranges = [7, 30, 90];

const days = computed(() => props.series.length);

const filterFields = computed(() => {
    const fields: { key: StatsFilterKey; label: string; options: string[] }[] =
        [
            {
                key: 'country',
                label: 'Country',
                options: props.options.countries,
            },
            { key: 'city', label: 'City', options: props.options.cities },
            {
                key: 'browser',
                label: 'Browser',
                options: props.options.browsers,
            },
            {
                key: 'os',
                label: 'Operating system',
                options: props.options.systems,
            },
            {
                key: 'device_type',
                label: 'Device',
                options: props.options.devices,
            },
        ];

    return fields.map((field) => {
        const active = props.filters[field.key];

        return active !== null && !field.options.includes(active)
            ? { ...field, options: [...field.options, active] }
            : field;
    });
});

const activeFilters = computed(() =>
    filterFields.value.filter((field) => props.filters[field.key] !== null),
);

const query = (filters: StatsFilters, period: string = props.period.period) => ({
    period,
    ...Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== null),
    ),
});

const visit = (filters: StatsFilters) =>
    router.get(stats(props.link.id, { query: query(filters) }), undefined, {
        preserveState: true,
        preserveScroll: true,
    });

const setFilter = (key: StatsFilterKey, value: string | null) =>
    visit({ ...props.filters, [key]: value || null });

const clearFilters = () =>
    visit({
        country: null,
        city: null,
        browser: null,
        os: null,
        device_type: null,
    });

const maxDaily = computed(() =>
    Math.max(1, ...props.series.map((day) => day.count)),
);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Links', href: index() }],
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
                    :variant="`${range}d` === period.period ? 'default' : 'outline'"
                    size="sm"
                    as-child
                >
                    <InertiaLink
                        :href="stats(link.id, { query: query(filters, `${range}d`) })"
                        preserve-scroll
                    >
                        {{ range }} days
                    </InertiaLink>
                </Button>
            </div>
        </div>

        <div class="flex flex-wrap items-end gap-3">
            <label
                v-for="field in filterFields"
                :key="field.key"
                class="flex flex-col gap-1 text-sm"
            >
                {{ field.label }}
                <select
                    class="rounded-md border bg-background px-2 py-1.5 text-sm"
                    :value="filters[field.key] ?? ''"
                    @change="
                        setFilter(
                            field.key,
                            ($event.target as HTMLSelectElement).value,
                        )
                    "
                >
                    <option value="">All</option>
                    <option
                        v-for="option in field.options"
                        :key="option"
                        :value="option"
                    >
                        {{ option }}
                    </option>
                </select>
            </label>
        </div>

        <div v-if="activeFilters.length > 0" class="flex flex-wrap gap-2">
            <span
                v-for="field in activeFilters"
                :key="field.key"
                class="inline-flex items-center gap-1 rounded-full border px-3 py-1 text-sm"
            >
                {{ field.label }}: {{ filters[field.key] }}
                <button
                    type="button"
                    class="ml-1 rounded-full px-1 hover:bg-muted"
                    :aria-label="`Remove ${field.label} filter`"
                    @click="setFilter(field.key, null)"
                >
                    ×
                </button>
            </span>
            <Button variant="outline" size="sm" @click="clearFilters"
                >Clear all</Button
            >
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

        <div class="grid gap-6 md:grid-cols-2">
            <section
                v-for="table in [
                    { title: 'Countries', rows: countries },
                    { title: 'Cities', rows: cities },
                    { title: 'Browsers', rows: browsers },
                    { title: 'Operating systems', rows: systems },
                    { title: 'Devices', rows: devices },
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
