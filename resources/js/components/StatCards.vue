<script setup lang="ts">
defineProps<{
    cards: { label: string; value: string | number }[];
}>();
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div
            v-for="card in cards"
            :key="card.label"
            class="rounded-lg border p-4"
        >
            <p class="text-sm text-muted-foreground">{{ card.label }}</p>
            <p class="text-2xl font-semibold tabular-nums">
                {{ card.value }}
            </p>
        </div>
    </div>
</template>
