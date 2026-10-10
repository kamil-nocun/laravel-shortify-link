<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import LinkForm from '@/components/LinkForm.vue';
import { create, index, store } from '@/routes/links';

defineProps<{ domains: { id: string; host: string }[]; canSetAlias: boolean }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'My links', href: index() },
            { title: 'Create', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="Create link" />

    <div class="max-w-2xl space-y-6 p-4">
        <Heading title="Create link" description="Add a short link" />
        <LinkForm
            :domains="domains"
            :can-set-alias="canSetAlias"
            submit-label="Create link"
            :action="(form) => form.post(store().url)"
        />
    </div>
</template>
