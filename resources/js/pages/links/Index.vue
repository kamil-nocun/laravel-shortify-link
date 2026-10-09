<script setup lang="ts">
import { Head, Link as InertiaLink, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { create, destroy, edit, index, stats } from '@/routes/links';
import type { Link, Paginated } from '@/types';

defineProps<{ links: Paginated<Link> }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Links', href: index() }],
    },
});
</script>

<template>
    <Head title="Links" />

    <div class="space-y-6 p-4">
        <div class="flex items-center justify-between">
            <Heading title="Links" description="Manage your short links" />
            <Button as-child>
                <InertiaLink :href="create()">New link</InertiaLink>
            </Button>
        </div>

        <p v-if="links.data.length === 0" class="text-sm text-muted-foreground">
            You have no links yet.
        </p>

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th class="p-3">Title</th>
                        <th class="p-3">Target URL</th>
                        <th class="p-3">Short URL</th>
                        <th class="p-3">Clicks</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Expires</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="link in links.data"
                        :key="link.id"
                        class="border-t"
                    >
                        <td class="p-3">{{ link.title ?? '—' }}</td>
                        <td class="max-w-64 truncate p-3">
                            {{ link.original_url }}
                        </td>
                        <td class="p-3 font-mono">{{ link.short_url }}</td>
                        <td class="p-3">{{ link.clicks_count ?? 0 }}</td>
                        <td class="p-3">
                            <Badge
                                :variant="
                                    link.is_active ? 'default' : 'secondary'
                                "
                                >{{
                                    link.is_active ? 'Active' : 'Inactive'
                                }}</Badge
                            >
                        </td>
                        <td class="p-3">
                            {{
                                link.expires_at
                                    ? new Date(link.expires_at).toLocaleString()
                                    : '—'
                            }}
                        </td>
                        <td class="space-x-2 p-3 text-right">
                            <Button variant="outline" size="sm" as-child>
                                <InertiaLink :href="stats(link.id)"
                                    >Stats</InertiaLink
                                >
                            </Button>
                            <Button variant="outline" size="sm" as-child>
                                <InertiaLink :href="edit(link.id)"
                                    >Edit</InertiaLink
                                >
                            </Button>
                            <Dialog>
                                <DialogTrigger as-child>
                                    <Button variant="destructive" size="sm"
                                        >Delete</Button
                                    >
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Delete link?</DialogTitle>
                                        <DialogDescription>
                                            The code {{ link.code }} will stop
                                            working. This cannot be undone.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <DialogFooter class="gap-2">
                                        <DialogClose as-child>
                                            <Button variant="secondary"
                                                >Cancel</Button
                                            >
                                        </DialogClose>
                                        <Button
                                            variant="destructive"
                                            @click="
                                                router.delete(
                                                    destroy(link.id).url,
                                                )
                                            "
                                            >Delete</Button
                                        >
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex gap-2">
            <Button
                v-if="links.prev_page_url"
                variant="outline"
                size="sm"
                as-child
            >
                <InertiaLink :href="links.prev_page_url">Previous</InertiaLink>
            </Button>
            <Button
                v-if="links.next_page_url"
                variant="outline"
                size="sm"
                as-child
            >
                <InertiaLink :href="links.next_page_url">Next</InertiaLink>
            </Button>
        </div>
    </div>
</template>
