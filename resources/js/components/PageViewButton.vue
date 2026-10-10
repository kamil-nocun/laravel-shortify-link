<script setup lang="ts">
import { ExternalLink } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { show } from '@/routes/content';
import type { PageStatus } from '@/types';

defineProps<{ slug: string; status: PageStatus; size?: 'default' | 'sm' }>();
</script>

<template>
    <Button
        v-if="status === 'published'"
        as-child
        variant="outline"
        :size="size"
    >
        <a :href="show(slug).url" target="_blank" rel="noopener">
            <ExternalLink class="size-4" /> View page
        </a>
    </Button>
    <Button
        v-else
        variant="outline"
        :size="size"
        disabled
        title="Enable the page to view it publicly"
    >
        <ExternalLink class="size-4" /> View page
    </Button>
</template>
