<script setup lang="ts">
import { Head, Link as InertiaLink, router, usePage } from "@inertiajs/vue3";
import { watchDebounced } from "@vueuse/core";
import { computed, ref } from "vue";
import Heading from "@/components/Heading.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { dashboard } from "@/routes/admin";
import {
    block,
    create,
    destroy,
    edit,
    index,
    show,
    unblock,
} from "@/routes/admin/users";
import type { AdminUser, Paginated } from "@/types";

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

const toggleBlock = (user: AdminUser) =>
    user.blocked_at
        ? router.delete(unblock(user.id).url, { preserveScroll: true })
        : router.patch(block(user.id).url, {}, { preserveScroll: true });

const deleteUser = (user: AdminUser) => {
    if (
        confirm(`Delete ${user.name}? Their links and statistics are removed.`)
    ) {
        router.delete(destroy(user.id).url, { preserveScroll: true });
    }
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: "Admin", href: dashboard() },
            { title: "Users", href: index() },
        ],
    },
});

const isSuperAdmin = computed(
    () => usePage().props.auth.user.role === "super_admin",
);
</script>

<template>
    <Head title="Users" />

    <div class="space-y-6 p-4">
        <div class="flex items-center justify-between">
            <Heading title="Users" description="Manage user accounts" />
            <Button as-child>
                <InertiaLink :href="create()">Add user</InertiaLink>
            </Button>
        </div>

        <Input
            v-model="search"
            type="search"
            aria-label="Search users"
            placeholder="Search by name or email"
            class="max-w-xs"
        />

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th class="p-3">Name</th>
                        <th class="p-3">Email</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Links</th>
                        <th class="p-3">Registered</th>
                        <th class="p-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="users.data.length === 0" class="border-t">
                        <td
                            colspan="6"
                            class="p-6 text-center text-muted-foreground"
                        >
                            {{
                                search
                                    ? "No users match your search."
                                    : "No users yet."
                            }}
                        </td>
                    </tr>
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
                        <td class="p-3">
                            <Badge
                                :variant="
                                    user.blocked_at
                                        ? 'destructive'
                                        : 'secondary'
                                "
                            >
                                {{ user.blocked_at ? "Blocked" : "Active" }}
                                <template v-if="user.blocked_by_name">
                                    by
                                    <InertiaLink
                                        v-if="
                                            isSuperAdmin && user.blocked_by_id
                                        "
                                        :href="show(user.blocked_by_id)"
                                        class="underline-offset-4 hover:underline"
                                    >
                                        {{ user.blocked_by_name }}
                                    </InertiaLink>
                                    <template v-else>{{
                                        user.blocked_by_name
                                    }}</template>
                                </template>
                            </Badge>
                        </td>
                        <td class="p-3 tabular-nums">{{ user.links_count }}</td>
                        <td class="p-3">
                            {{ new Date(user.created_at).toLocaleDateString() }}
                        </td>
                        <td class="p-3">
                            <div class="flex gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <InertiaLink :href="show(user.id)">
                                        View
                                    </InertiaLink>
                                </Button>
                                <Button
                                    v-if="user.can.update"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                >
                                    <InertiaLink :href="edit(user.id)">
                                        Edit
                                    </InertiaLink>
                                </Button>
                                <Button
                                    v-if="user.can.block"
                                    variant="outline"
                                    size="sm"
                                    @click="toggleBlock(user)"
                                >
                                    {{ user.blocked_at ? "Unblock" : "Block" }}
                                </Button>
                                <Button
                                    v-if="user.can.delete"
                                    variant="destructive"
                                    size="sm"
                                    @click="deleteUser(user)"
                                >
                                    Delete
                                </Button>
                            </div>
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
