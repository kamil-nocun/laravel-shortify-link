<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { front_app } from '@/routes';

defineOptions({
    layout: {
        title: 'Link not found',
        description: 'This link does not exist or is no longer available.',
    },
});
</script>

<template>
    <Head title="Link not found" />

    <div class="text-center">
        <TextLink :href="front_app()" class="text-sm">Go to homepage</TextLink>
    </div>
</template>
