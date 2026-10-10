<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import LinkForm from '@/components/LinkForm.vue';
import { dashboard } from '@/routes/admin';
import { index, show } from '@/routes/admin/users';
import { store } from '@/routes/admin/users/links';

const props = defineProps<{ user: { id: string; name: string }; domains: { id: string; host: string }[]; canSetAlias: boolean }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: dashboard() },
            { title: 'Users', href: index() },
        ],
    },
});
</script>

<template>
    <Head :title="`Add link for ${props.user.name}`" />

    <div class="max-w-2xl space-y-6 p-4">
        <Heading
            :title="`Add link for ${props.user.name}`"
            description="The user will own this link and can edit it"
        />
        <LinkForm
            :domains="props.domains"
            :can-set-alias="props.canSetAlias"
            submit-label="Create link"
            :action="(form) => form.post(store(props.user.id).url)"
        />
    </div>
</template>
