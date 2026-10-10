<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{ page: { title: string; body: string } }>();
</script>

<template>
    <Head :title="page.title" />

    <main class="mx-auto max-w-3xl px-4 py-12">
        <h1 class="mb-6 text-3xl font-semibold tracking-tight">{{ page.title }}</h1>
        <!-- eslint-disable-next-line vue/no-v-html -->
        <div class="cms-content" v-html="page.body" />
    </main>
</template>

<style scoped>
.cms-content :deep(h1),
.cms-content :deep(h2),
.cms-content :deep(h3),
.cms-content :deep(h4) {
    margin: 1.5rem 0 0.75rem;
    font-weight: 600;
}

.cms-content :deep(h2) {
    font-size: 1.5rem;
}

.cms-content :deep(h3) {
    font-size: 1.25rem;
}

.cms-content :deep(p),
.cms-content :deep(ul),
.cms-content :deep(ol),
.cms-content :deep(blockquote),
.cms-content :deep(pre) {
    margin: 0.75rem 0;
}

.cms-content :deep(ul) {
    list-style: disc;
    padding-left: 1.5rem;
}

.cms-content :deep(ol) {
    list-style: decimal;
    padding-left: 1.5rem;
}

.cms-content :deep(a) {
    text-decoration: underline;
}

.cms-content :deep(blockquote) {
    border-left: 3px solid currentColor;
    padding-left: 1rem;
    opacity: 0.8;
}
</style>
