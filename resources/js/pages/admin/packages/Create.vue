<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import PackageForm from '@/components/PackageForm.vue';
import { dashboard } from '@/routes/admin';
import { create, index, store } from '@/routes/admin/packages';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: dashboard() },
            { title: 'Packages', href: index() },
            { title: 'Create', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="Create package" />

    <div class="max-w-2xl space-y-6 p-4">
        <Heading
            title="Create package"
            description="Set the link limit and the price for each billing period"
        />
        <PackageForm
            submit-label="Create package"
            :action="(form) => form.post(store().url)"
        />
    </div>
</template>
