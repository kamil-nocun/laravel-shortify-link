<script setup lang="ts">
import { Head, Link as InertiaLink, router } from '@inertiajs/vue3';
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
import { create, destroy, edit, index } from '@/routes/admin/roles';
import type { RoleSummary } from '@/types';

defineProps<{ roles: RoleSummary[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: dashboard() },
            { title: 'Roles', href: index() },
        ],
    },
});

function canDelete(role: RoleSummary): boolean {
    return !role.is_super && !role.is_default && role.users_count === 0;
}
</script>

<template>
    <Head title="Roles" />

    <div class="space-y-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                title="Roles"
                description="Bundles of permissions assigned to users"
            />
            <Button as-child>
                <InertiaLink :href="create()">Create role</InertiaLink>
            </Button>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th class="p-3">Name</th>
                        <th class="p-3">Description</th>
                        <th class="p-3">Permissions</th>
                        <th class="p-3">Users</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="role in roles" :key="role.id" class="border-t">
                        <td class="p-3">
                            {{ role.name }}
                            <Badge v-if="role.is_super" class="ml-2"
                                >Super admin</Badge
                            >
                            <Badge
                                v-if="role.is_default"
                                variant="secondary"
                                class="ml-2"
                                >Default</Badge
                            >
                        </td>
                        <td class="p-3">{{ role.description }}</td>
                        <td class="p-3">
                            {{
                                role.is_super ? 'All' : role.permissions_count
                            }}
                        </td>
                        <td class="p-3">{{ role.users_count }}</td>
                        <td class="space-x-2 p-3 text-right">
                            <Button variant="outline" size="sm" as-child>
                                <InertiaLink :href="edit(role.id)">{{
                                    role.is_super ? 'View' : 'Edit'
                                }}</InertiaLink>
                            </Button>
                            <Dialog v-if="canDelete(role)">
                                <DialogTrigger as-child>
                                    <Button variant="destructive" size="sm"
                                        >Delete</Button
                                    >
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Delete role?</DialogTitle>
                                        <DialogDescription
                                            >{{ role.name }} will be removed
                                            permanently.</DialogDescription
                                        >
                                    </DialogHeader>
                                    <DialogFooter class="gap-2">
                                        <DialogClose as-child>
                                            <Button variant="secondary"
                                                >Cancel</Button
                                            >
                                        </DialogClose>
                                        <DialogClose as-child>
                                            <Button
                                                variant="destructive"
                                                @click="
                                                    router.delete(
                                                        destroy(role.id).url,
                                                    )
                                                "
                                                >Delete</Button
                                            >
                                        </DialogClose>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
