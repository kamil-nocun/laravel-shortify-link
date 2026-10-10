<script setup lang="ts">
import { Head, Link as InertiaLink } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Activity, Link2, MousePointerClick, Users } from '@lucide/vue';
import ClicksCard from '@/components/ClicksCard.vue';
import Heading from '@/components/Heading.vue';
import PeriodSelector from '@/components/PeriodSelector.vue';
import StatCards from '@/components/StatCards.vue';
import TopLinks from '@/components/TopLinks.vue';
import { periodLabel } from '@/lib/periodLabel';
import { dashboard } from '@/routes/admin';
import { stats as linkStats } from '@/routes/admin/links';
import { show as showUser } from '@/routes/admin/users';
import type {
    AdminCounters,
    DailyClicks,
    StatsPeriodParams,
    TopLink,
    TopUser,
} from '@/types';

const props = defineProps<{
    period: StatsPeriodParams;
    granularity: 'day' | 'month';
    total: number;
    series: DailyClicks[];
    counters: AdminCounters & { users: number };
    topLinks: TopLink[];
    topUsers: TopUser[];
}>();

const cards = computed(() => [
    { label: 'Users', value: props.counters.users, icon: Users },
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
        breadcrumbs: [{ title: 'Admin', href: dashboard() }],
    },
});
</script>

<template>
    <Head title="Admin" />

    <div class="space-y-6 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <Heading title="Admin" description="System overview" />
            <PeriodSelector :current="period.period" :url="dashboard().url" />
        </div>

        <StatCards :cards="cards" />

        <ClicksCard
            :series="series"
            :granularity="granularity"
            :total="total"
        />

        <div class="grid items-start gap-6 lg:grid-cols-2">
            <TopLinks as-table :links="topLinks" :href="linkStats" />

            <section class="overflow-hidden rounded-lg border">
                <h2 class="px-4 py-3 font-medium">Top users</h2>
                <p
                    v-if="topUsers.length === 0"
                    class="px-4 pb-4 text-sm text-muted-foreground"
                >
                    No data
                </p>
                <table v-else class="w-full text-left text-sm">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th class="p-3">User</th>
                            <th class="p-3 text-right">Clicks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in topUsers"
                            :key="user.id"
                            class="border-t"
                        >
                            <td class="p-3">
                                <InertiaLink
                                    :href="showUser(user.id)"
                                    class="underline-offset-4 hover:underline"
                                >
                                    {{ user.name }}
                                </InertiaLink>
                                <span class="block text-muted-foreground">
                                    {{ user.email }}
                                </span>
                            </td>
                            <td class="p-3 text-right tabular-nums">
                                {{ user.clicks }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</template>
