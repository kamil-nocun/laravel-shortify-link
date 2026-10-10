<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    cards: {
        label: string;
        value: string | number;
        hint?: string;
        icon?: Component;
    }[];
}>();
</script>

<template>
    <div
        class="grid gap-4 sm:grid-cols-2"
        :class="cards.length === 3 ? 'lg:grid-cols-3' : 'lg:grid-cols-4'"
    >
        <div
            v-for="card in cards"
            :key="card.label"
            class="flex items-start justify-between gap-3 rounded-lg border p-4"
        >
            <div>
                <p class="text-sm text-muted-foreground">{{ card.label }}</p>
                <p class="text-3xl font-semibold tabular-nums">
                    {{ card.value }}
                </p>
                <p v-if="card.hint" class="text-xs text-muted-foreground">
                    {{ card.hint }}
                </p>
            </div>
            <div v-if="card.icon" class="rounded-md bg-muted p-2">
                <component :is="card.icon" class="size-5" />
            </div>
        </div>
    </div>
</template>
