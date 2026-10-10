<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import RoleForm from '@/components/RoleForm.vue';
import { dashboard } from '@/routes/admin';
import { create, index, store } from '@/routes/admin/roles';
import type { PermissionGroup } from '@/types';

defineProps<{ permissionGroups: PermissionGroup[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: dashboard() },
            { title: 'Roles', href: index() },
            { title: 'Create', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="Create role" />

    <div class="max-w-6xl space-y-6 p-4">
        <Heading
            title="Create role"
            description="Choose the permissions this role grants"
        />
        <RoleForm
            :permission-groups="permissionGroups"
            submit-label="Create role"
            :action="(form) => form.post(store().url)"
        />
    </div>
</template>
