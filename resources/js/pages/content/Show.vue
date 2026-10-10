<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/components/PublicLayout.vue';

defineProps<{
    page: { title: string; body: string };
    legalDocuments: { title: string; slug: string }[];
}>();
</script>

<template>
    <Head :title="page.title" />

    <PublicLayout :legal-documents="legalDocuments">
        <article class="mx-auto max-w-3xl px-4 py-12">
            <h1 class="mb-6 text-3xl font-semibold tracking-tight">
                {{ page.title }}
            </h1>
            <!-- eslint-disable-next-line vue/no-v-html -->
            <div class="cms-content" v-html="page.body" />
        </article>
    </PublicLayout>
</template>
