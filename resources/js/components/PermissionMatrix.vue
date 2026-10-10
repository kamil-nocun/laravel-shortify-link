<script setup lang="ts">
import { ChevronDown, Minus } from '@lucide/vue';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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

function groupState(group: PermissionGroup): boolean | 'indeterminate' {
    const selected = keysOf(group).filter((key) =>
        props.modelValue.includes(key),
    ).length;

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
    <div class="space-y-3">
        <Collapsible
            v-for="group in groups"
            :key="group.group"
            :default-open="true"
            class="rounded-lg border"
        >
            <div class="flex items-center gap-2 p-3">
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
                <CollapsibleTrigger as-child>
                    <button
                        type="button"
                        class="ml-auto text-muted-foreground"
                        :aria-label="`Toggle ${group.group}`"
                    >
                        <ChevronDown class="size-4" />
                    </button>
                </CollapsibleTrigger>
            </div>
            <CollapsibleContent>
                <div class="grid gap-2 border-t p-3 sm:grid-cols-2">
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
            </CollapsibleContent>
        </Collapsible>
    </div>
</template>
