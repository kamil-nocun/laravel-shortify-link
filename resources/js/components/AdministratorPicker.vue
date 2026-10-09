<script setup lang="ts">
import { Label } from '@/components/ui/label';
import type { AdministratorOption } from '@/types';

defineProps<{ administrators: AdministratorOption[] }>();

const selected = defineModel<string[]>({ default: () => [] });
</script>

<template>
    <div class="grid gap-2">
        <label
            v-for="administrator in administrators"
            :key="administrator.id"
            class="flex items-center gap-2 text-sm"
        >
            <input
                v-model="selected"
                type="checkbox"
                name="administrators[]"
                :value="administrator.id"
            />
            <Label as="span">
                {{ administrator.name }}
                <span class="text-muted-foreground">
                    {{ administrator.email }}
                </span>
            </Label>
        </label>
    </div>
</template>
