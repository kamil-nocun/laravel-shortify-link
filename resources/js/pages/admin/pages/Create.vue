<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import PageForm from '@/components/PageForm.vue';
import { dashboard } from '@/routes/admin';
import { create, index, store } from '@/routes/admin/pages';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: dashboard() },
            { title: 'Pages', href: index() },
            { title: 'Create', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="Create page" />

    <div class="max-w-3xl space-y-6 p-4">
        <Heading
            title="Create page"
            description="The page stays a draft until you publish it"
        />
        <PageForm
            submit-label="Create page"
            :action="(form) => form.post(store().url)"
        />
    </div>
</template>
