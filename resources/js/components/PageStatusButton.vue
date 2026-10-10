<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { status as statusRoute } from '@/routes/admin/pages';
import type { PageStatus } from '@/types';

const props = defineProps<{
    id: string;
    status: PageStatus;
    size?: 'default' | 'sm';
    disabled?: boolean;
}>();

function toggle(): void {
    router.patch(
        statusRoute(props.id).url,
        { status: props.status === 'published' ? 'draft' : 'published' },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Button
        variant="secondary"
        :size="size"
        :disabled="disabled"
        :title="disabled ? 'Save your changes first' : undefined"
        @click="toggle"
        >{{ status === 'published' ? 'Disable' : 'Enable' }}</Button
    >
</template>
