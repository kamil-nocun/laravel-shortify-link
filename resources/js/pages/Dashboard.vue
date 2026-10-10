<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ClicksChart from '@/components/ClicksChart.vue';
import Heading from '@/components/Heading.vue';
import PackageCard from '@/components/PackageCard.vue';
import PeriodSelector from '@/components/PeriodSelector.vue';
import StatCards from '@/components/StatCards.vue';
import TopLinksTable from '@/components/TopLinksTable.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { dashboard } from '@/routes';
import { create as createLink, stats as linkStats } from '@/routes/links';
import type {
    AdminCounters,
    DailyClicks,
    DashboardPackage,
    StatsPeriodParams,
    TopLink,
} from '@/types';

const props = defineProps<{
    period: StatsPeriodParams;
    granularity: 'day' | 'month';
    total: number;
    series: DailyClicks[];
    counters: AdminCounters;
    topLinks: TopLink[];
    package: DashboardPackage | null;
}>();

const { can } = usePermissions();

const cards = computed(() => [
    { label: 'Links', value: props.counters.links },
    {
        label: 'Active / disabled links',
        value: `${props.counters.activeLinks} / ${props.counters.disabledLinks}`,
    },
    { label: 'Clicks', value: props.counters.clicks },
]);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="space-y-6 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <Heading title="Dashboard" description="Your links at a glance" />
            <div class="flex flex-wrap items-center gap-2">
                <PeriodSelector
                    :current="period.period"
                    :url="dashboard().url"
                />
                <Button v-if="can('links.manage')" size="sm" as-child>
                    <Link :href="createLink()">New link</Link>
                </Button>
            </div>
        </div>

        <StatCards :cards="cards" />

        <p v-if="total === 0" class="text-sm text-muted-foreground">
            No clicks in this period yet.
        </p>
        <ClicksChart v-else :series="series" :granularity="granularity" />

        <div class="grid gap-6 md:grid-cols-2">
            <TopLinksTable :links="topLinks" :href="linkStats" />
            <PackageCard v-if="package" :package="package" />
        </div>
    </div>
</template>
