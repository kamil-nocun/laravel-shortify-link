<script setup lang="ts">
import { Head, Link as InertiaLink } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes/admin';
import { create, edit, index } from '@/routes/admin/pages';
import type { PageListItem } from '@/types';

defineProps<{ pages: PageListItem[] }>();

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
    <Head title="Pages" />

    <div class="space-y-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                title="Pages"
                description="Public content pages available under /content"
            />
            <Button as-child>
                <InertiaLink :href="create()">New page</InertiaLink>
            </Button>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th class="p-3">Title</th>
                        <th class="p-3">Address</th>
                        <th class="p-3">Status</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="page in pages" :key="page.id" class="border-t">
                        <td class="p-3">{{ page.title ?? '—' }}</td>
                        <td class="p-3">/content/{{ page.slug }}</td>
                        <td class="p-3">
                            <Badge
                                :variant="
                                    page.status === 'published'
                                        ? 'default'
                                        : 'secondary'
                                "
                                >{{ page.status }}</Badge
                            >
                        </td>
                        <td class="p-3 text-right">
                            <Button as-child variant="outline" size="sm">
                                <InertiaLink :href="edit(page.id)"
                                    >Edit</InertiaLink
                                >
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="pages.length === 0">
                        <td class="p-3 text-muted-foreground" colspan="4">
                            No pages yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
