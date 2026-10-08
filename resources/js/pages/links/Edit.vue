<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import LinkForm from '@/components/LinkForm.vue';
import { edit, index, update } from '@/routes/links';
import type { Link } from '@/types';

const props = defineProps<{ link: Link }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Links', href: index() }],
    },
});
</script>

<template>
    <Head title="Edit link" />

    <div class="max-w-2xl space-y-6 p-4">
        <Heading title="Edit link" :description="props.link.code" />
        <LinkForm
            :link="props.link"
            submit-label="Save changes"
            :action="(form) => form.patch(update(props.link.id).url)"
        />
    </div>
</template>
