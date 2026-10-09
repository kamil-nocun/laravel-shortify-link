<script setup lang="ts">
import { Head, Link as InertiaLink, router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes/admin';
import { index, show } from '@/routes/admin/users';
import type { AdminUser, Paginated } from '@/types';

const props = defineProps<{ users: Paginated<AdminUser>; search: string }>();

const search = ref(props.search);

watchDebounced(
    search,
    (value) =>
        router.get(index().url, value ? { search: value } : {}, {
            preserveState: true,
            replace: true,
        }),
    { debounce: 300 },
);

const roleLabels: Record<AdminUser['role'], string> = {
    user: 'User',
    admin: 'Admin',
    super_admin: 'Super admin',
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: dashboard() },
            { title: 'Users', href: index() },
        ],
    },
});
</script>

<template>
    <Head title="Users" />

    <div class="space-y-6 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <Heading title="Users" description="Manage user accounts" />
            <Input
                v-model="search"
                type="search"
                aria-label="Search users"
                placeholder="Search by name or email"
                class="max-w-xs"
            />
        </div>

        <p v-if="users.data.length === 0" class="text-sm text-muted-foreground">
            No data
        </p>

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th class="p-3">Name</th>
                        <th class="p-3">Email</th>
                        <th class="p-3">Role</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Links</th>
                        <th class="p-3">Registered</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="user in users.data"
                        :key="user.id"
                        class="border-t"
                    >
                        <td class="p-3">
                            <InertiaLink
                                :href="show(user.id)"
                                class="underline-offset-4 hover:underline"
                            >
                                {{ user.name }}
                            </InertiaLink>
                        </td>
                        <td class="p-3">{{ user.email }}</td>
                        <td class="p-3">{{ roleLabels[user.role] }}</td>
                        <td class="p-3">
                            <Badge
                                :variant="
                                    user.blocked_at
                                        ? 'destructive'
                                        : 'secondary'
                                "
                            >
                                {{ user.blocked_at ? 'Blocked' : 'Active' }}
                            </Badge>
                        </td>
                        <td class="p-3 tabular-nums">{{ user.links_count }}</td>
                        <td class="p-3">
                            {{ new Date(user.created_at).toLocaleDateString() }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex gap-2">
            <Button
                v-if="users.prev_page_url"
                variant="outline"
                size="sm"
                as-child
            >
                <InertiaLink :href="users.prev_page_url">Previous</InertiaLink>
            </Button>
            <Button
                v-if="users.next_page_url"
                variant="outline"
                size="sm"
                as-child
            >
                <InertiaLink :href="users.next_page_url">Next</InertiaLink>
            </Button>
        </div>
    </div>
</template>
