<script setup lang="ts">
import {
    Head,
    Link as InertiaLink,
    router,
    setLayoutProps,
} from '@inertiajs/vue3';
import { computed } from 'vue';
import ClicksChart from '@/components/ClicksChart.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { dashboard } from '@/routes/admin';
import { disable, enable, stats as linkStats } from '@/routes/admin/links';
import {
    block,
    destroy,
    index,
    role as updateRole,
    show,
    unblock,
} from '@/routes/admin/users';
import type {
    AdminCounters,
    AdminUser,
    DailyClicks,
    Link,
    Paginated,
    StatsPeriodParams,
} from '@/types';

const props = defineProps<{
    user: AdminUser;
    links: Paginated<Link>;
    period: StatsPeriodParams;
    granularity: 'day' | 'month';
    total: number;
    series: DailyClicks[];
    counters: AdminCounters;
}>();

const ranges = [7, 30, 90];

const roleLabels: Record<AdminUser['role'], string> = {
    user: 'User',
    admin: 'Admin',
    super_admin: 'Super admin',
};

const cards = computed(() => [
    { label: 'Links', value: props.counters.links },
    {
        label: 'Active / disabled links',
        value: `${props.counters.activeLinks} / ${props.counters.disabledLinks}`,
    },
    { label: 'Clicks', value: props.counters.clicks },
]);

const setPeriod = (range: number) =>
    router.get(
        show(props.user.id).url,
        { period: `${range}d` },
        { preserveScroll: true },
    );

const toggleBlock = () =>
    props.user.blocked_at
        ? router.delete(unblock(props.user.id).url, { preserveScroll: true })
        : router.patch(block(props.user.id).url, {}, { preserveScroll: true });

const changeRole = (value: string) =>
    router.patch(
        updateRole(props.user.id).url,
        { role: value },
        { preserveScroll: true },
    );

const toggleLink = (link: Link) =>
    link.disabled_by_admin_at
        ? router.delete(enable(link.id).url, { preserveScroll: true })
        : router.patch(disable(link.id).url, {}, { preserveScroll: true });

const linkStatus = (link: Link) =>
    link.disabled_by_admin_at
        ? 'Disabled by admin'
        : link.is_active
          ? 'Active'
          : 'Disabled by owner';

setLayoutProps({
    breadcrumbs: [
        { title: 'Admin', href: dashboard() },
        { title: 'Users', href: index() },
        { title: props.user.name, href: show(props.user.id) },
    ],
});
</script>

<template>
    <Head :title="user.name" />

    <div class="space-y-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <Heading :title="user.name" :description="user.email" />
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <Badge variant="outline">{{ roleLabels[user.role] }}</Badge>
                    <Badge
                        :variant="user.blocked_at ? 'destructive' : 'secondary'"
                    >
                        {{ user.blocked_at ? 'Blocked' : 'Active' }}
                    </Badge>
                    <span class="text-muted-foreground">
                        Registered
                        {{ new Date(user.created_at).toLocaleDateString() }}
                    </span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <select
                    v-if="user.can.changeRole"
                    aria-label="Role"
                    class="rounded-md border bg-background px-2 py-1.5 text-sm"
                    :value="user.role"
                    @change="
                        changeRole(($event.target as HTMLSelectElement).value)
                    "
                >
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
                <Button
                    v-if="user.can.block"
                    variant="outline"
                    size="sm"
                    @click="toggleBlock"
                >
                    {{ user.blocked_at ? 'Unblock' : 'Block' }}
                </Button>
                <Dialog v-if="user.can.delete">
                    <DialogTrigger as-child>
                        <Button variant="destructive" size="sm">Delete</Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Delete user?</DialogTitle>
                            <DialogDescription>
                                {{ user.name }} and all their links and stats
                                will be deleted. This cannot be undone.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>
                            <Button
                                variant="destructive"
                                @click="router.delete(destroy(user.id).url)"
                                >Delete</Button
                            >
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-medium">Statistics</h2>
            <div class="flex flex-wrap gap-2">
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
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
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

        <h2 class="font-medium">Links</h2>

        <p v-if="links.data.length === 0" class="text-sm text-muted-foreground">
            No data
        </p>

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th class="p-3">Title</th>
                        <th class="p-3">Code</th>
                        <th class="p-3">Clicks</th>
                        <th class="p-3">Status</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="link in links.data"
                        :key="link.id"
                        class="border-t"
                    >
                        <td class="p-3">{{ link.title ?? '—' }}</td>
                        <td class="p-3 font-mono">{{ link.code }}</td>
                        <td class="p-3 tabular-nums">
                            {{ link.clicks_count ?? 0 }}
                        </td>
                        <td class="p-3">
                            <Badge
                                :variant="
                                    link.disabled_by_admin_at
                                        ? 'destructive'
                                        : link.is_active
                                          ? 'secondary'
                                          : 'outline'
                                "
                            >
                                {{ linkStatus(link) }}
                            </Badge>
                        </td>
                        <td class="space-x-2 p-3 text-right whitespace-nowrap">
                            <Button
                                variant="outline"
                                size="sm"
                                :aria-label="`${link.disabled_by_admin_at ? 'Enable' : 'Disable'} link ${link.code}`"
                                @click="toggleLink(link)"
                            >
                                {{
                                    link.disabled_by_admin_at
                                        ? 'Enable'
                                        : 'Disable'
                                }}
                            </Button>
                            <Button variant="outline" size="sm" as-child>
                                <InertiaLink
                                    :href="linkStats(link.id)"
                                    :aria-label="`Stats of link ${link.code}`"
                                    >Stats</InertiaLink
                                >
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex gap-2">
            <Button
                v-if="links.prev_page_url"
                variant="outline"
                size="sm"
                as-child
            >
                <InertiaLink :href="links.prev_page_url">Previous</InertiaLink>
            </Button>
            <Button
                v-if="links.next_page_url"
                variant="outline"
                size="sm"
                as-child
            >
                <InertiaLink :href="links.next_page_url">Next</InertiaLink>
            </Button>
        </div>
    </div>
</template>
