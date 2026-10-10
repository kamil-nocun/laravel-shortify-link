<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import RoleForm from '@/components/RoleForm.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { dashboard } from '@/routes/admin';
import { index, update } from '@/routes/admin/roles';
import type { PermissionGroup, Role } from '@/types';

const props = defineProps<{
    role: Role;
    permissionGroups: PermissionGroup[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: dashboard() },
            { title: 'Roles', href: index() },
        ],
    },
});
</script>

<template>
    <Head title="Edit role" />

    <div class="max-w-6xl space-y-6 p-4">
        <Heading title="Edit role" :description="props.role.name" />
        <Alert v-if="props.role.is_super">
            <ShieldCheck />
            <AlertTitle>Super admin role</AlertTitle>
            <AlertDescription>
                It always has every permission and cannot be edited.
            </AlertDescription>
        </Alert>
        <RoleForm
            :role="props.role"
            :permission-groups="permissionGroups"
            :readonly="props.role.is_super"
            submit-label="Save changes"
            :action="(form) => form.put(update(props.role.id).url)"
        />
    </div>
</template>
