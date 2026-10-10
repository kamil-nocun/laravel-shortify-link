<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{ page: { title: string; body: string } }>();
</script>

<template>
    <Head :title="page.title" />

    <main class="mx-auto max-w-3xl px-4 py-12">
        <h1 class="mb-6 text-3xl font-semibold tracking-tight">
            {{ page.title }}
        </h1>
        <!-- eslint-disable-next-line vue/no-v-html -->
        <div class="cms-content" v-html="page.body" />
    </main>
</template>
