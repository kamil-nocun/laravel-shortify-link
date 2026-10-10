<script setup lang="ts">
import { Head, Link as InertiaLink, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PageForm from '@/components/PageForm.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes/admin';
import { index, status, update } from '@/routes/admin/pages';
import { index as versionsIndex } from '@/routes/admin/pages/versions';
import type { PageContent } from '@/types';

const props = defineProps<{
    page: PageContent;
    errors: Record<string, string>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: dashboard() },
            { title: 'Pages', href: index() },
        ],
    },
});

function toggleStatus(): void {
    router.patch(status(props.page.id).url, {
        status: props.page.status === 'published' ? 'draft' : 'published',
    });
}
</script>

<template>
    <Head title="Edit page" />

    <div class="max-w-3xl space-y-6 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <Heading
                title="Edit page"
                :description="`/content/${props.page.slug} (${props.page.status})`"
            />
            <div class="flex gap-2">
                <Button as-child variant="outline">
                    <InertiaLink :href="versionsIndex(props.page.id)"
                        >History</InertiaLink
                    >
                </Button>
                <Button variant="secondary" @click="toggleStatus">{{
                    props.page.status === 'published' ? 'Unpublish' : 'Publish'
                }}</Button>
            </div>
        </div>
        <InputError :message="errors.status" />
        <PageForm
            :page="props.page"
            submit-label="Save changes"
            :action="(form) => form.put(update(props.page.id).url)"
        />
    </div>
</template>
