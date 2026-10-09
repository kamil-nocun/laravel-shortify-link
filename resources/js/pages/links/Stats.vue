<script setup lang="ts">
import { Head, useHttp } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import StatsTable from '@/components/StatsTable.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/links';
import type { RouteQueryOptions } from '@/wayfinder';
import { data as statsData } from '@/routes/links/stats';
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

const initial = defineProps<{
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

type StatsQuery = NonNullable<RouteQueryOptions['query']>;

type Section = 'summary' | 'options' | StatsTableKey;

const link = computed(() => initial.link);
const period = ref(initial.period);
const granularity = ref(initial.granularity);
const sort = ref(initial.sort);
const total = ref(initial.total);
const series = ref(initial.series);
const filters = ref(initial.filters);
const options = ref(initial.options);
const tables = ref<Record<StatsTableKey, StatsTableData>>({
    countries: initial.countries,
    cities: initial.cities,
    browsers: initial.browsers,
    systems: initial.systems,
    devices: initial.devices,
});

const pendingSections = ref(new Set<Section>());
const isLoading = (section: Section) => pendingSections.value.has(section);

/** Requested state; updated immediately so rapid changes build on each other. */
const wanted = reactive({
    period: { ...initial.period } as StatsPeriodParams,
    filters: { ...initial.filters } as StatsFilters,
    sort: { ...initial.sort } as StatsSorts,
    perPage: Object.fromEntries(
        tableKeys.map((key) => [key, initial[key].perPage]),
    ) as Record<StatsTableKey, number>,
    pages: Object.fromEntries(
        tableKeys.map((key) => [key, initial[key].page]),
    ) as Record<StatsTableKey, number>,
});

const resyncWanted = () => {
    wanted.period = { ...period.value };
    wanted.filters = { ...filters.value };
    wanted.sort = { ...sort.value };

    for (const key of tableKeys) {
        wanted.perPage[key] = tables.value[key].perPage;
        wanted.pages[key] = tables.value[key].page;
    }
};

/** Reseeds all local state when the component is reused for another link. */
watch(
    () => initial.link.id,
    () => {
        http.cancel();
        requestId++;
        pendingSections.value = new Set();
        period.value = initial.period;
        granularity.value = initial.granularity;
        sort.value = initial.sort;
        total.value = initial.total;
        series.value = initial.series;
        filters.value = initial.filters;
        options.value = initial.options;
        for (const key of tableKeys) {
            tables.value[key] = initial[key];
        }
        resyncWanted();
    },
);

const customTypes = ['day', 'month', 'year', 'range'];

const customType = ref(
    customTypes.includes(period.value.period) ? period.value.period : '',
);

const rangeDraft = ref<{ from?: string; to?: string }>({});

watch(
    () => period.value.period,
    (value) => {
        customType.value = customTypes.includes(value) ? value : '';
        rangeDraft.value = {};
    },
);

watch(customType, () => {
    rangeDraft.value = {};
});

const buildQuery = (sections: Section[] | null) => {
    const current = wanted.period;
    const query: StatsQuery = { period: current.period };

    for (const key of ['date', 'month', 'year', 'from', 'to'] as const) {
        const relevant =
            current.period === 'range'
                ? key === 'from' || key === 'to'
                : current.period === key ||
                  (key === 'date' && current.period === 'day');

        if (relevant && current[key]) {
            query[key] = current[key];
        }
    }

    for (const [key, value] of Object.entries(wanted.filters)) {
        if (value !== null) {
            query[key] = value;
        }
    }

    for (const key of tableKeys) {
        query[`${key}_per_page`] = wanted.perPage[key];
        query[`${key}_page`] = wanted.pages[key];
    }

    query.sort = { ...wanted.sort };

    if (sections) {
        query.sections = sections;
    }

    return query;
};

let query: StatsQuery = {};
let requestId = 0;

const statsUrl = () => statsData.url(link.value.id, { query });

const http = useHttp<Record<string, never>, Record<string, unknown>>(
    'get',
    statsUrl,
    {},
);

const merge = (response: Record<string, unknown>) => {
    if (response.period) {
        period.value = response.period as StatsPeriodParams;
    }

    if (response.granularity) {
        granularity.value = response.granularity as 'day' | 'month';
    }

    if (response.series) {
        series.value = response.series as DailyClicks[];
    }

    if (response.total !== undefined) {
        total.value = response.total as number;
    }

    if (response.filters) {
        filters.value = response.filters as StatsFilters;
    }

    if (response.options) {
        options.value = response.options as StatsOptions;
    }

    if (response.sort) {
        sort.value = response.sort as StatsSorts;
    }

    for (const key of tableKeys) {
        if (response[key]) {
            tables.value[key] = response[key] as StatsTableData;
        }
    }
};

/** Loads the given sections (all when null); a newer request supersedes the in-flight one. */
const load = (sections: Section[] | null) => {
    const all: Section[] = ['summary', 'options', ...tableKeys];

    if (sections === null) {
        pendingSections.value = new Set(all);
    } else {
        sections.forEach((section) => pendingSections.value.add(section));
    }

    const pending = pendingSections.value;
    const id = ++requestId;

    query = buildQuery(pending.size === all.length ? null : [...pending]);
    http.cancel();
    http.get(statsUrl())
        .then((response) => {
            if (id !== requestId) {
                return;
            }

            merge(response);
            sort.value = { ...wanted.sort };
            resyncWanted();
            pendingSections.value = new Set();
        })
        .catch(() => {
            if (id !== requestId) {
                return;
            }

            resyncWanted();
            pendingSections.value = new Set();
        });
};

const periodErrors = computed(() =>
    (['date', 'month', 'year', 'from', 'to'] as const)
        .map((key) => ({
            key,
            message: (http.errors as Record<string, string>)[key],
        }))
        .filter((error) => error.message),
);

const errorFor = (key: string) =>
    periodErrors.value.some((error) => error.key === key);

const errorId = (key: string) => (errorFor(key) ? `error-${key}` : undefined);

const periodLabel = computed(() => {
    const { period: kind, from, to } = period.value;

    if (/^\d+d$/.test(kind)) {
        return `in the last ${kind.slice(0, -1)} days`;
    }

    if (kind === 'all') {
        return 'in total';
    }

    return `in ${kind === 'range' ? `${from} - ${to}` : (period.value[kind === 'day' ? 'date' : (kind as 'month' | 'year')] ?? '')}`;
});

const filterFields = computed(() => {
    const fields: { key: StatsFilterKey; label: string; options: string[] }[] =
        [
            {
                key: 'country',
                label: 'Country',
                options: options.value.countries,
            },
            { key: 'city', label: 'City', options: options.value.cities },
            {
                key: 'browser',
                label: 'Browser',
                options: options.value.browsers,
            },
            {
                key: 'os',
                label: 'Operating system',
                options: options.value.systems,
            },
            {
                key: 'device_type',
                label: 'Device',
                options: options.value.devices,
            },
        ];

    return fields.map((field) => {
        const active = filters.value[field.key];

        return active !== null && !field.options.includes(active)
            ? { ...field, options: [...field.options, active] }
            : field;
    });
});

const activeFilters = computed(() =>
    filterFields.value.filter((field) => filters.value[field.key] !== null),
);

const resetPages = () => {
    for (const key of tableKeys) {
        wanted.pages[key] = 1;
    }
};

const changePeriod = (next: Partial<StatsPeriodParams>) => {
    wanted.period = { ...wanted.period, ...next } as StatsPeriodParams;
    resetPages();
    load(null);
};

const setPeriodValue = (key: keyof StatsPeriodParams, value: string) => {
    if (customType.value !== 'range') {
        if (value) {
            wanted.period = { period: customType.value } as StatsPeriodParams;
            changePeriod({ [key]: value });
        }

        return;
    }

    rangeDraft.value = { ...rangeDraft.value, [key]: value };

    const from = rangeDraft.value.from ?? period.value.from;
    const to = rangeDraft.value.to ?? period.value.to;

    if (from && to) {
        wanted.period = { period: 'range' } as StatsPeriodParams;
        changePeriod({ from, to });
    }
};

const setPreset = (kind: string) => {
    wanted.period = { period: kind } as StatsPeriodParams;
    changePeriod({});
};

const setFilter = (key: StatsFilterKey, value: string | null) => {
    wanted.filters = { ...wanted.filters, [key]: value || null };
    resetPages();
    load(null);
};

const clearFilters = () => {
    wanted.filters = {
        country: null,
        city: null,
        browser: null,
        os: null,
        device_type: null,
    };
    resetPages();
    load(null);
};

const sortTable = (key: StatsTableKey, value: string) => {
    wanted.sort[key] = value;
    wanted.pages[key] = 1;
    load([key]);
};

const limitTable = (key: StatsTableKey, perPage: number) => {
    wanted.perPage[key] = perPage;
    wanted.pages[key] = 1;
    load([key]);
};

const pageTable = (key: StatsTableKey, page: number) => {
    wanted.pages[key] = page;
    load([key]);
};

const maxDaily = computed(() =>
    Math.max(1, ...series.value.map((day) => day.count)),
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
                    @click="setPreset(`${range}d`)"
                >
                    {{ range }} days
                </Button>
                <Button
                    :variant="period.period === 'all' ? 'default' : 'outline'"
                    size="sm"
                    @click="setPreset('all')"
                >
                    All time
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
                    :aria-invalid="errorFor('date')"
                    :aria-describedby="errorId('date')"
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
                    :aria-invalid="errorFor('month')"
                    :aria-describedby="errorId('month')"
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
                    :aria-invalid="errorFor('year')"
                    :aria-describedby="errorId('year')"
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
                        :aria-invalid="errorFor('from')"
                        :aria-describedby="errorId('from')"
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
                        :aria-invalid="errorFor('to')"
                        :aria-describedby="errorId('to')"
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
            :id="`error-${error.key}`"
            :key="error.key"
            class="text-sm text-destructive"
            role="alert"
        >
            {{ error.message }}
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

        <p
            class="text-3xl font-semibold transition-opacity"
            :class="{ 'animate-pulse opacity-50': isLoading('summary') }"
        >
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
            class="flex h-40 items-end gap-px transition-opacity"
            :class="{ 'animate-pulse opacity-50': isLoading('summary') }"
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

        <div class="space-y-6">
            <StatsTable
                v-for="key in tableKeys"
                :key="key"
                :title="tableTitles[key]"
                :table="tables[key]"
                :sort="wanted.sort[key]"
                :loading="isLoading(key)"
                @sort="sortTable(key, $event)"
                @page="pageTable(key, $event)"
                @per-page="limitTable(key, $event)"
            />
        </div>
    </div>
</template>
