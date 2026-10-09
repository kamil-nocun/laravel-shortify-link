<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import PackageForm from '@/components/PackageForm.vue';
import { dashboard } from '@/routes/admin';
import { index, update } from '@/routes/admin/packages';
import type { Package } from '@/types';

const props = defineProps<{ package: Package }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: dashboard() },
            { title: 'Packages', href: index() },
        ],
    },
});
</script>

<template>
    <Head title="Edit package" />

    <div class="max-w-2xl space-y-6 p-4">
        <Heading title="Edit package" :description="props.package.name" />
        <PackageForm
            :package="props.package"
            submit-label="Save changes"
            :action="(form) => form.put(update(props.package.id).url)"
        />
    </div>
</template>
