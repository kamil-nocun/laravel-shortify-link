<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import RoleForm from '@/components/RoleForm.vue';
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

    <div class="max-w-3xl space-y-6 p-4">
        <Heading title="Edit role" :description="props.role.name" />
        <p v-if="props.role.is_super" class="text-sm text-muted-foreground">
            The Super admin role always has every permission and cannot be
            edited.
        </p>
        <RoleForm
            :role="props.role"
            :permission-groups="permissionGroups"
            :readonly="props.role.is_super"
            submit-label="Save changes"
            :action="(form) => form.put(update(props.role.id).url)"
        />
    </div>
</template>
