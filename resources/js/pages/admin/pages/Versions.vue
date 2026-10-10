<script setup lang="ts">
import { Head, Link as InertiaLink, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes/admin';
import { edit, index } from '@/routes/admin/pages';
import { restore, show } from '@/routes/admin/pages/versions';
import type { PageVersionListItem } from '@/types';

const props = defineProps<{
    page: { id: string; slug: string };
    versions: PageVersionListItem[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: dashboard() },
            { title: 'Pages', href: index() },
        ],
    },
});

function restoreVersion(versionId: string): void {
    if (window.confirm('Restore this version as the current one?')) {
        router.post(restore({ page: props.page.id, version: versionId }).url);
    }
}
</script>

<template>
    <Head title="Page history" />

    <div class="max-w-3xl space-y-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                title="Page history"
                :description="`/content/${props.page.slug}`"
            />
            <Button as-child variant="outline">
                <InertiaLink :href="edit(props.page.id)"
                    >Back to edit</InertiaLink
                >
            </Button>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th class="p-3">Title</th>
                        <th class="p-3">Saved</th>
                        <th class="p-3">Author</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="version in versions"
                        :key="version.id"
                        class="border-t"
                    >
                        <td class="p-3">
                            {{ version.title }}
                            <Badge v-if="version.is_current" class="ml-2"
                                >current</Badge
                            >
                        </td>
                        <td class="p-3">
                            {{ new Date(version.created_at).toLocaleString() }}
                        </td>
                        <td class="p-3">{{ version.author_name ?? '—' }}</td>
                        <td class="flex justify-end gap-2 p-3">
                            <Button as-child variant="outline" size="sm">
                                <InertiaLink
                                    :href="
                                        show({
                                            page: props.page.id,
                                            version: version.id,
                                        })
                                    "
                                    >View</InertiaLink
                                >
                            </Button>
                            <Button
                                v-if="!version.is_current"
                                variant="secondary"
                                size="sm"
                                @click="restoreVersion(version.id)"
                                >Restore</Button
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
