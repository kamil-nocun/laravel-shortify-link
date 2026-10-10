<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PermissionMatrix from '@/components/PermissionMatrix.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PermissionGroup, Role } from '@/types';

const props = defineProps<{
    role?: Role;
    permissionGroups: PermissionGroup[];
    submitLabel: string;
    readonly?: boolean;
    action: (form: ReturnType<typeof useRoleForm>) => void;
}>();

function useRoleForm() {
    const allKeys = props.permissionGroups.flatMap((group) =>
        group.permissions.map((permission) => permission.key),
    );

    return useForm({
        name: props.role?.name ?? '',
        description: props.role?.description ?? '',
        permissions: props.role?.is_super
            ? allKeys
            : [...(props.role?.permissions ?? [])],
    });
}

const form = useRoleForm();
</script>

<template>
    <form class="space-y-6" @submit.prevent="action(form)">
        <div class="grid gap-2">
            <Label for="name">Name</Label>
            <Input id="name" v-model="form.name" :disabled="readonly" required />
            <InputError :message="form.errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="description">Description</Label>
            <textarea
                id="description"
                v-model="form.description"
                rows="3"
                :disabled="readonly"
                class="flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
            />
            <InputError :message="form.errors.description" />
        </div>

        <div class="grid gap-2">
            <Label>Permissions</Label>
            <PermissionMatrix
                v-model="form.permissions"
                :groups="permissionGroups"
                :disabled="readonly"
            />
            <InputError :message="form.errors.permissions" />
        </div>

        <Button v-if="!readonly" type="submit" :disabled="form.processing">{{
            submitLabel
        }}</Button>
    </form>
</template>
