<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Activity, Link2, MousePointerClick } from '@lucide/vue';
import ClicksCard from '@/components/ClicksCard.vue';
import Heading from '@/components/Heading.vue';
import LinkStatusTable from '@/components/LinkStatusTable.vue';
import PackageCard from '@/components/PackageCard.vue';
import PeriodSelector from '@/components/PeriodSelector.vue';
import StatCards from '@/components/StatCards.vue';
import TopLinks from '@/components/TopLinks.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { periodLabel } from '@/lib/periodLabel';
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
    { label: 'Links', value: props.counters.links, icon: Link2 },
    {
        label: 'Active links',
        value: props.counters.activeLinks,
        hint: `${props.counters.disabledLinks} disabled`,
        icon: Activity,
    },
    {
        label: 'Clicks',
        value: props.counters.clicks,
        hint: periodLabel(props.period.period),
        icon: MousePointerClick,
    },
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

        <div v-if="counters.links === 0" class="space-y-6">
            <div class="rounded-lg border p-8 text-center">
                <p class="text-lg font-medium">Create your first link</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Shorten a URL and watch its clicks show up here.
                </p>
                <Button v-if="can('links.manage')" class="mt-4" as-child>
                    <Link :href="createLink()">New link</Link>
                </Button>
            </div>
            <PackageCard v-if="package" :package="package" />
        </div>

        <template v-else>
            <StatCards :cards="cards" />

            <div class="grid items-start gap-6 lg:grid-cols-3">
                <ClicksCard
                    class="lg:col-span-2"
                    :series="series"
                    :granularity="granularity"
                    :total="total"
                />
                <div class="space-y-6">
                    <TopLinks as-table :links="topLinks" :href="linkStats" />
                    <LinkStatusTable
                        :active="counters.activeLinks"
                        :disabled="counters.disabledLinks"
                    />
                    <PackageCard v-if="package" :package="package" />
                </div>
            </div>
        </template>
    </div>
</template>
