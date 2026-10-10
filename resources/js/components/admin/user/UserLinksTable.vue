<script setup lang="ts">
import { Link as InertiaLink, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { disable, enable, stats as linkStats } from '@/routes/admin/links';
import { show } from '@/routes/admin/users';
import { create as createLink } from '@/routes/admin/users/links';
import type { AdminUser, Link, Paginated } from '@/types';

defineProps<{
    user: AdminUser;
    links: Paginated<Link>;
}>();

const isSuperAdmin = computed(() => usePage().props.auth.isSuperAdmin);

const toggleLink = (link: Link) =>
    link.disabled_by_admin_at
        ? router.delete(enable(link.id).url, { preserveScroll: true })
        : router.patch(disable(link.id).url, {}, { preserveScroll: true });

const linkStatus = (link: Link) =>
    link.disabled_by_admin_at
        ? 'Disabled by'
        : link.is_active
          ? 'Active'
          : 'Disabled by owner';
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="font-medium">Links</h2>
            <Button v-if="user.can.createLink" size="sm" as-child>
                <InertiaLink :href="createLink(user.id)">Add link</InertiaLink>
            </Button>
        </div>

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
                                <template v-if="link.disabled_by_admin_at">
                                    <InertiaLink
                                        v-if="isSuperAdmin && link.disabled_by"
                                        :href="show(link.disabled_by)"
                                        class="underline-offset-4 hover:underline"
                                    >
                                        {{ link.disabled_by_name }}
                                    </InertiaLink>
                                    <template v-else>{{
                                        link.disabled_by_name ?? 'admin'
                                    }}</template>
                                </template>
                            </Badge>
                        </td>
                        <td class="space-x-2 p-3 text-right whitespace-nowrap">
                            <Button
                                v-if="user.can.disableLinks"
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
