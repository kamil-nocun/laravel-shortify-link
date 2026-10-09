<script setup lang="ts">
import { Head, Link as InertiaLink, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import StatsTable from '@/components/StatsTable.vue';
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
    StatsSorts,
    StatsTable as StatsTableData,
    StatsTableKey,
} from '@/types';

const props = defineProps<{
    link: Link;
    period: StatsPeriodParams;
    granularity: 'day' | 'month';
    sort: StatsSorts;
    total: number;
    series: DailyClicks[];
    countries: StatsTableData;
    cities: StatsTableData;
    browsers: StatsTableData;
    systems: StatsTableData;
    devices: StatsTableData;
    filters: StatsFilters;
    options: StatsOptions;
}>();

const ranges = [7, 30, 90];

const tableKeys: StatsTableKey[] = [
    'countries',
    'cities',
    'browsers',
    'systems',
    'devices',
];

const tableTitles: Record<StatsTableKey, string> = {
    countries: 'Countries',
    cities: 'Cities',
    browsers: 'Browsers',
    systems: 'Operating systems',
    devices: 'Devices',
};

const rangeDraft = ref<{ from?: string; to?: string }>({});

const customTypes = ['day', 'month', 'year', 'range'];

const customType = ref(
    customTypes.includes(props.period.period) ? props.period.period : '',
);

watch(
    () => props.period.period,
    (value) => {
        customType.value = customTypes.includes(value) ? value : '';
        rangeDraft.value = {};
    },
);

watch(customType, () => {
    rangeDraft.value = {};
});

const page = usePage();

const periodErrors = computed(() =>
    ['date', 'month', 'year', 'from', 'to']
        .map((key) => (page.props.errors as Record<string, string>)?.[key])
        .filter(Boolean),
);

const periodLabel = computed(() => {
    const { period, from, to } = props.period;

    if (/^\d+d$/.test(period)) {
        return `in the last ${period.slice(0, -1)} days`;
    }

    if (period === 'all') {
        return 'in total';
    }

    return `in ${period === 'range' ? `${from} - ${to}` : (props.period[period === 'day' ? 'date' : (period as 'month' | 'year')] ?? '')}`;
});

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

type VisitOverrides = {
    period?: Partial<StatsPeriodParams>;
    filters?: StatsFilters;
    sort?: [StatsTableKey, string];
    perPage?: [StatsTableKey, number];
    page?: [StatsTableKey, number];
};

const buildQuery = (overrides: VisitOverrides = {}) => {
    const period = { ...props.period, ...overrides.period };
    const filters = overrides.filters ?? props.filters;
    const resetPages = !overrides.page && !overrides.perPage;
    const sort: Record<string, string> = {};
    const query: Record<string, string | number | Record<string, string>> = {
        period: period.period,
    };

    for (const key of ['date', 'month', 'year', 'from', 'to'] as const) {
        const relevant =
            period.period === 'range'
                ? key === 'from' || key === 'to'
                : period.period === key ||
                  (key === 'date' && period.period === 'day');

        if (relevant && period[key]) {
            query[key] = period[key];
        }
    }

    for (const [key, value] of Object.entries(filters)) {
        if (value !== null) {
            query[key] = value;
        }
    }

    for (const key of tableKeys) {
        sort[key] =
            overrides.sort?.[0] === key ? overrides.sort[1] : props.sort[key];

        const perPage =
            overrides.perPage?.[0] === key
                ? overrides.perPage[1]
                : props[key].perPage;
        const page = resetPages
            ? 1
            : overrides.page?.[0] === key
              ? overrides.page[1]
              : overrides.perPage?.[0] === key
                ? 1
                : props[key].page;

        if (perPage !== 10) {
            query[`${key}_per_page`] = perPage;
        }

        if (page > 1) {
            query[`${key}_page`] = page;
        }
    }

    query.sort = sort;

    return query;
};

const visit = (overrides: VisitOverrides = {}) =>
    router.get(
        stats(props.link.id, { query: buildQuery(overrides) }),
        undefined,
        {
            preserveState: true,
            preserveScroll: true,
        },
    );

const setPeriodValue = (key: keyof StatsPeriodParams, value: string) => {
    if (customType.value !== 'range') {
        if (value) {
            visit({ period: { period: customType.value, [key]: value } });
        }

        return;
    }

    rangeDraft.value = { ...rangeDraft.value, [key]: value };

    const from = rangeDraft.value.from ?? props.period.from;
    const to = rangeDraft.value.to ?? props.period.to;

    if (from && to) {
        visit({ period: { period: 'range', from, to } });
    }
};

const setFilter = (key: StatsFilterKey, value: string | null) =>
    visit({ filters: { ...props.filters, [key]: value || null } });

const clearFilters = () =>
    visit({
        filters: {
            country: null,
            city: null,
            browser: null,
            os: null,
            device_type: null,
        },
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
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-for="range in ranges"
                    :key="range"
                    :variant="
                        `${range}d` === period.period ? 'default' : 'outline'
                    "
                    size="sm"
                    as-child
                >
                    <InertiaLink
                        :href="
                            stats(link.id, {
                                query: buildQuery({
                                    period: { period: `${range}d` },
                                }),
                            })
                        "
                        preserve-scroll
                    >
                        {{ range }} days
                    </InertiaLink>
                </Button>
                <Button
                    :variant="period.period === 'all' ? 'default' : 'outline'"
                    size="sm"
                    as-child
                >
                    <InertiaLink
                        :href="
                            stats(link.id, {
                                query: buildQuery({
                                    period: { period: 'all' },
                                }),
                            })
                        "
                        preserve-scroll
                    >
                        All time
                    </InertiaLink>
                </Button>
                <select
                    aria-label="Custom period"
                    class="rounded-md border bg-background px-2 py-1.5 text-sm"
                    v-model="customType"
                >
                    <option value="" disabled>Custom period</option>
                    <option value="day">Day</option>
                    <option value="month">Month</option>
                    <option value="year">Year</option>
                    <option value="range">Range</option>
                </select>
                <input
                    v-if="customType === 'day'"
                    type="date"
                    aria-label="Day"
                    class="rounded-md border bg-background px-2 py-1 text-sm"
                    :value="period.date ?? ''"
                    @change="
                        setPeriodValue(
                            'date',
                            ($event.target as HTMLInputElement).value,
                        )
                    "
                />
                <input
                    v-else-if="customType === 'month'"
                    type="month"
                    aria-label="Month"
                    class="rounded-md border bg-background px-2 py-1 text-sm"
                    :value="period.month ?? ''"
                    @change="
                        setPeriodValue(
                            'month',
                            ($event.target as HTMLInputElement).value,
                        )
                    "
                />
                <input
                    v-else-if="customType === 'year'"
                    type="number"
                    min="1970"
                    max="9999"
                    aria-label="Year"
                    class="w-24 rounded-md border bg-background px-2 py-1 text-sm"
                    :value="period.year ?? ''"
                    @change="
                        /^\d{4}$/.test(
                            ($event.target as HTMLInputElement).value,
                        ) &&
                        setPeriodValue(
                            'year',
                            ($event.target as HTMLInputElement).value,
                        )
                    "
                />
                <template v-else-if="customType === 'range'">
                    <input
                        type="date"
                        aria-label="From"
                        class="rounded-md border bg-background px-2 py-1 text-sm"
                        :value="period.from ?? ''"
                        :max="rangeDraft.to ?? period.to ?? undefined"
                        @change="
                            setPeriodValue(
                                'from',
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                    />
                    <input
                        type="date"
                        aria-label="To"
                        class="rounded-md border bg-background px-2 py-1 text-sm"
                        :value="period.to ?? ''"
                        :min="rangeDraft.from ?? period.from ?? undefined"
                        @change="
                            setPeriodValue(
                                'to',
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                    />
                </template>
            </div>
        </div>
        <p
            v-for="error in periodErrors"
            :key="error"
            class="text-sm text-destructive"
            role="alert"
        >
            {{ error }}
        </p>

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
                >clicks {{ periodLabel }}</span
            >
        </p>

        <p v-if="total === 0" class="text-sm text-muted-foreground">
            No clicks in this period yet.
        </p>

        <div
            v-else
            class="flex h-40 items-end gap-px"
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

        <div class="grid gap-6 md:grid-cols-2">
            <StatsTable
                v-for="key in tableKeys"
                :key="key"
                :title="tableTitles[key]"
                :table="props[key]"
                :sort="sort[key]"
                @sort="visit({ sort: [key, $event] })"
                @page="visit({ page: [key, $event] })"
                @per-page="visit({ perPage: [key, $event] })"
            />
        </div>
    </div>
</template>
