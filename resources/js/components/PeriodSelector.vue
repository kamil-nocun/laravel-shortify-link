<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    current: string;
    url: string;
}>();

const ranges = [7, 30, 90];

const setPeriod = (range: number) =>
    router.get(props.url, { period: `${range}d` }, { preserveScroll: true });
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <Button
            v-for="range in ranges"
            :key="range"
            :variant="`${range}d` === current ? 'default' : 'outline'"
            size="sm"
            :aria-pressed="`${range}d` === current"
            @click="setPeriod(range)"
        >
            {{ range }} days
        </Button>
    </div>
</template>
