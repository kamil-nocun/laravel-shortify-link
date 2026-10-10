<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import PermissionMatrix from '@/components/PermissionMatrix.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/admin/roles';
import type { PermissionGroup, Role } from '@/types';

const props = defineProps<{
    role?: Role;
    permissionGroups: PermissionGroup[];
    submitLabel: string;
    readonly?: boolean;
    action: (form: ReturnType<typeof useRoleForm>) => void;
}>();

const allKeys = computed(() =>
    props.permissionGroups.flatMap((group) =>
        group.permissions.map((permission) => permission.key),
    ),
);

function useRoleForm() {
    return useForm({
        name: props.role?.name ?? '',
        description: props.role?.description ?? '',
        permissions: props.role?.is_super
            ? [...allKeys.value]
            : [...(props.role?.permissions ?? [])],
    });
}

const form = useRoleForm();

const selectedShare = computed(() =>
    allKeys.value.length === 0
        ? 0
        : (form.permissions.length / allKeys.value.length) * 100,
);
</script>

<template>
    <form
        class="grid items-start gap-6 lg:grid-cols-[20rem_1fr]"
        @submit.prevent="action(form)"
    >
        <aside class="space-y-5 rounded-lg border p-4 lg:sticky lg:top-4">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    :disabled="readonly"
                    required
                />
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

            <div class="space-y-1.5">
                <div class="flex justify-between text-sm">
                    <span>Permissions selected</span>
                    <span class="text-muted-foreground tabular-nums">
                        {{ form.permissions.length }} / {{ allKeys.length }}
                    </span>
                </div>
                <div
                    class="h-2 overflow-hidden rounded-full bg-muted"
                    role="progressbar"
                    aria-label="Permissions selected"
                    aria-valuemin="0"
                    :aria-valuemax="allKeys.length"
                    :aria-valuenow="form.permissions.length"
                >
                    <div
                        class="h-full bg-primary"
                        :style="{ width: `${selectedShare}%` }"
                    />
                </div>
            </div>

            <div v-if="!readonly" class="flex flex-wrap gap-2">
                <Button type="submit" :disabled="form.processing">{{
                    submitLabel
                }}</Button>
                <Button variant="outline" as-child>
                    <Link :href="index()">Cancel</Link>
                </Button>
            </div>
            <Button v-else variant="outline" as-child>
                <Link :href="index()">Back to roles</Link>
            </Button>
        </aside>

        <div class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-medium">Permissions</h2>
                <div v-if="!readonly" class="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="form.permissions = [...allKeys]"
                    >
                        Select all
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="form.permissions = []"
                    >
                        Clear
                    </Button>
                </div>
            </div>
            <InputError :message="form.errors.permissions" />
            <PermissionMatrix
                v-model="form.permissions"
                :groups="permissionGroups"
                :disabled="readonly"
            />
        </div>
    </form>
</template>
