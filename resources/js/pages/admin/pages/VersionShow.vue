<script setup lang="ts">
import { Head, Link as InertiaLink } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes/admin';
import { index } from '@/routes/admin/pages';
import { index as versionsIndex } from '@/routes/admin/pages/versions';

const props = defineProps<{
    page: { id: string; slug: string };
    version: { id: string; title: string; body: string; created_at: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: dashboard() },
            { title: 'Pages', href: index() },
        ],
    },
});
</script>

<template>
    <Head title="Page version" />

    <div class="max-w-3xl space-y-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                :title="props.version.title"
                :description="
                    new Date(props.version.created_at).toLocaleString()
                "
            />
            <Button as-child variant="outline">
                <InertiaLink :href="versionsIndex(props.page.id)"
                    >Back to history</InertiaLink
                >
            </Button>
        </div>
        <!-- eslint-disable-next-line vue/no-v-html -->
        <div
            class="cms-content rounded-lg border p-4"
            v-html="props.version.body"
        />
    </div>
</template>
