<script setup lang="ts">
import { Link as InertiaLink, router, usePage } from '@inertiajs/vue3';
import { computed, ref, useTemplateRef } from 'vue';
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
import {
    block,
    destroy,
    role as updateRole,
    show,
    unblock,
} from '@/routes/admin/users';
import type { AdminUser } from '@/types';

const props = defineProps<{
    user: AdminUser;
    effectiveLimit: number | null;
    roleOptions: { client: string; administrator: string };
}>();

const isSuperAdmin = computed(() => usePage().props.auth.isSuperAdmin);

const toggleBlock = () =>
    props.user.blocked_at
        ? router.delete(unblock(props.user.id).url, { preserveScroll: true })
        : router.patch(block(props.user.id).url, {}, { preserveScroll: true });

const isAdministrator = computed(
    () => props.user.role_id === props.roleOptions.administrator,
);
const targetRoleLabel = computed(() =>
    isAdministrator.value ? 'Client' : 'Administrator',
);

const isRoleOpen = ref(false);

const changeRole = () =>
    router.patch(
        updateRole(props.user.id).url,
        {
            role_id: isAdministrator.value
                ? props.roleOptions.client
                : props.roleOptions.administrator,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                isRoleOpen.value = false;
            },
        },
    );

const isDeleteOpen = ref(false);
const isDeleting = ref(false);
const cancelButton = useTemplateRef<{ $el: HTMLElement }>('cancelButton');

const focusCancel = (event: Event) => {
    event.preventDefault();
    cancelButton.value?.$el.focus();
};

const deleteUser = () =>
    router.delete(destroy(props.user.id).url, {
        onStart: () => {
            isDeleting.value = true;
        },
        onFinish: () => {
            isDeleting.value = false;
            isDeleteOpen.value = false;
        },
    });
</script>

<template>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-2">
            <Heading :title="user.name" :description="user.email" />
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <Badge variant="outline">{{ user.role }}</Badge>
                <Badge :variant="user.blocked_at ? 'destructive' : 'secondary'">
                    {{ user.blocked_at ? 'Blocked' : 'Active' }}
                    <template v-if="user.blocked_by_name">
                        by
                        <InertiaLink
                            v-if="isSuperAdmin && user.blocked_by_id"
                            :href="show(user.blocked_by_id)"
                            class="underline-offset-4 hover:underline"
                        >
                            {{ user.blocked_by_name }}
                        </InertiaLink>
                        <template v-else>{{ user.blocked_by_name }}</template>
                    </template>
                </Badge>
                <span class="text-muted-foreground">
                    Registered
                    {{ new Date(user.created_at).toLocaleDateString() }}
                </span>
                <span class="text-muted-foreground">
                    Link limit:
                    {{ effectiveLimit === null ? 'no limit' : effectiveLimit }}
                </span>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Dialog v-if="user.can.changeRole" v-model:open="isRoleOpen">
                <DialogTrigger as-child>
                    <Button variant="outline" size="sm">
                        Change role to {{ targetRoleLabel }}
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Change role?</DialogTitle>
                        <DialogDescription>
                            {{ user.name }} will become {{ targetRoleLabel }}.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary">Cancel</Button>
                        </DialogClose>
                        <Button @click="changeRole"
                            >Change role to {{ targetRoleLabel }}</Button
                        >
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <Button
                v-if="user.can.block"
                variant="outline"
                size="sm"
                @click="toggleBlock"
            >
                {{ user.blocked_at ? 'Unblock' : 'Block' }}
            </Button>
            <Dialog v-if="user.can.delete" v-model:open="isDeleteOpen">
                <DialogTrigger as-child>
                    <Button variant="destructive" size="sm">Delete</Button>
                </DialogTrigger>
                <DialogContent @open-auto-focus="focusCancel">
                    <DialogHeader>
                        <DialogTitle>Delete user?</DialogTitle>
                        <DialogDescription>
                            {{ user.name }} and all their links and stats will
                            be deleted. This cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button ref="cancelButton" variant="secondary"
                                >Cancel</Button
                            >
                        </DialogClose>
                        <Button
                            variant="destructive"
                            :disabled="isDeleting"
                            @click="deleteUser"
                            >Delete</Button
                        >
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
