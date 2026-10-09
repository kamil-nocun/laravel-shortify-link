<script setup lang="ts">
import { Head, Link as InertiaLink, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import ClicksChart from '@/components/ClicksChart.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes/admin';
import { stats as linkStats } from '@/routes/admin/links';
import { index as usersIndex, show as showUser } from '@/routes/admin/users';
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

const ranges = [7, 30, 90];

const setPeriod = (range: number) =>
    router.get(
        dashboard().url,
        { period: `${range}d` },
        { preserveScroll: true },
    );

const cards = computed(() => [
    { label: 'Users', value: props.counters.users },
    { label: 'Links', value: props.counters.links },
    {
        label: 'Active / disabled links',
        value: `${props.counters.activeLinks} / ${props.counters.disabledLinks}`,
    },
    { label: 'Clicks', value: props.counters.clicks },
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
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-for="range in ranges"
                    :key="range"
                    :variant="
                        `${range}d` === period.period ? 'default' : 'outline'
                    "
                    size="sm"
                    :aria-pressed="`${range}d` === period.period"
                    @click="setPeriod(range)"
                >
                    {{ range }} days
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <InertiaLink :href="usersIndex()">Users</InertiaLink>
                </Button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="card in cards"
                :key="card.label"
                class="rounded-lg border p-4"
            >
                <p class="text-sm text-muted-foreground">{{ card.label }}</p>
                <p class="text-2xl font-semibold tabular-nums">
                    {{ card.value }}
                </p>
            </div>
        </div>

        <p v-if="total === 0" class="text-sm text-muted-foreground">
            No clicks in this period yet.
        </p>
        <ClicksChart v-else :series="series" :granularity="granularity" />

        <div class="grid gap-6 md:grid-cols-2">
            <section class="overflow-hidden rounded-lg border">
                <h2 class="px-4 py-3 font-medium">Top links</h2>
                <p
                    v-if="topLinks.length === 0"
                    class="px-4 pb-4 text-sm text-muted-foreground"
                >
                    No data
                </p>
                <table v-else class="w-full text-left text-sm">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th class="p-3">Link</th>
                            <th class="p-3 text-right">Clicks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="link in topLinks"
                            :key="link.id"
                            class="border-t"
                        >
                            <td class="p-3">
                                <InertiaLink
                                    :href="linkStats(link.id)"
                                    class="underline-offset-4 hover:underline"
                                >
                                    {{ link.title ?? link.code }}
                                </InertiaLink>
                            </td>
                            <td class="p-3 text-right tabular-nums">
                                {{ link.clicks }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

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
