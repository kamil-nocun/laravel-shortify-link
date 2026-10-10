<script setup lang="ts">
import { Minus } from '@lucide/vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { PermissionGroup } from '@/types';

const props = defineProps<{
    groups: PermissionGroup[];
    modelValue: string[];
    disabled?: boolean;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: string[]] }>();

function keysOf(group: PermissionGroup): string[] {
    return group.permissions.map((permission) => permission.key);
}

function selectedCount(group: PermissionGroup): number {
    return keysOf(group).filter((key) => props.modelValue.includes(key))
        .length;
}

function groupState(group: PermissionGroup): boolean | 'indeterminate' {
    const selected = selectedCount(group);

    if (selected === 0) {
        return false;
    }

    return selected === group.permissions.length ? true : 'indeterminate';
}

function toggleGroup(group: PermissionGroup, checked: boolean): void {
    const keys = keysOf(group);
    const rest = props.modelValue.filter((key) => !keys.includes(key));

    emit('update:modelValue', checked ? [...rest, ...keys] : rest);
}

function toggle(key: string, checked: boolean): void {
    emit(
        'update:modelValue',
        checked
            ? [...props.modelValue, key]
            : props.modelValue.filter((existing) => existing !== key),
    );
}
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2">
        <section
            v-for="group in groups"
            :key="group.group"
            class="overflow-hidden rounded-lg border"
        >
            <div class="flex items-center gap-2 border-b bg-muted/40 p-3">
                <Checkbox
                    :id="`group-${group.group}`"
                    :model-value="groupState(group)"
                    :disabled="disabled"
                    @update:model-value="toggleGroup(group, $event === true)"
                >
                    <template #default="{ modelValue }">
                        <Minus
                            v-if="modelValue === 'indeterminate'"
                            class="size-3.5"
                        />
                    </template>
                </Checkbox>
                <Label :for="`group-${group.group}`" class="font-medium">{{
                    group.group
                }}</Label>
                <span class="ml-auto text-xs text-muted-foreground tabular-nums">
                    {{ selectedCount(group) }} / {{ group.permissions.length }}
                </span>
            </div>
            <div class="space-y-2 p-3">
                <div
                    v-for="permission in group.permissions"
                    :key="permission.key"
                    class="flex items-center gap-2"
                >
                    <Checkbox
                        :id="`perm-${permission.key}`"
                        :model-value="modelValue.includes(permission.key)"
                        :disabled="disabled"
                        @update:model-value="
                            toggle(permission.key, $event === true)
                        "
                    />
                    <Label :for="`perm-${permission.key}`">{{
                        permission.label
                    }}</Label>
                </div>
            </div>
        </section>
    </div>
</template>
