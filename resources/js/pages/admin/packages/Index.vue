<script setup lang="ts">
import { Head, Link as InertiaLink, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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
import { dashboard } from '@/routes/admin';
import { create, destroy, edit, index } from '@/routes/admin/packages';
import { formatPrice } from '@/types';
import type { Package } from '@/types';

defineProps<{ packages: Package[]; errors: Record<string, string> }>();

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
    <Head title="Packages" />

    <div class="space-y-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                title="Packages"
                description="Link limits and prices assigned to users"
            />
            <Button as-child>
                <InertiaLink :href="create()">New package</InertiaLink>
            </Button>
        </div>

        <InputError :message="errors.package" />

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th class="p-3">Name</th>
                        <th class="p-3">Link limit</th>
                        <th class="p-3">One-time</th>
                        <th class="p-3">Monthly</th>
                        <th class="p-3">Quarterly</th>
                        <th class="p-3">Yearly</th>
                        <th class="p-3">Users</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="pkg in packages" :key="pkg.id" class="border-t">
                        <td class="p-3">
                            {{ pkg.name }}
                            <Badge v-if="pkg.is_default" class="ml-2"
                                >Default</Badge
                            >
                        </td>
                        <td class="p-3">{{ pkg.link_limit }}</td>
                        <td class="p-3">{{ formatPrice(pkg.price_one_time) }}</td>
                        <td class="p-3">{{ formatPrice(pkg.price_monthly) }}</td>
                        <td class="p-3">
                            {{ formatPrice(pkg.price_quarterly) }}
                        </td>
                        <td class="p-3">{{ formatPrice(pkg.price_yearly) }}</td>
                        <td class="p-3">{{ pkg.assignments_count ?? 0 }}</td>
                        <td class="space-x-2 p-3 text-right">
                            <Button variant="outline" size="sm" as-child>
                                <InertiaLink :href="edit(pkg.id)"
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
                                        <DialogTitle>Delete package?</DialogTitle>
                                        <DialogDescription>
                                            {{ pkg.name }} will be removed. The
                                            default and assigned packages
                                            cannot be deleted.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <DialogFooter class="gap-2">
                                        <DialogClose as-child>
                                            <Button variant="secondary"
                                                >Cancel</Button
                                            >
                                        </DialogClose>
                                        <DialogClose as-child>
                                            <Button
                                                variant="destructive"
                                                @click="
                                                    router.delete(
                                                        destroy(pkg.id).url,
                                                    )
                                                "
                                                >Delete</Button
                                            >
                                        </DialogClose>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
