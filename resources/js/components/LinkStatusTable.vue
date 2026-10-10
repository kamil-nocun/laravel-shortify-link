<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    active: number;
    disabled: number;
}>();

const rows = computed(() => {
    const total = props.active + props.disabled;
    const share = (count: number) =>
        total > 0 ? Math.round((count / total) * 100) : 0;

    return [
        { label: 'Active', count: props.active, share: share(props.active) },
        {
            label: 'Disabled',
            count: props.disabled,
            share: share(props.disabled),
        },
    ];
});
</script>

<template>
    <section class="overflow-hidden rounded-lg border">
        <h2 class="px-4 py-3 font-medium">Links by status</h2>
        <table class="w-full text-left text-sm">
            <thead class="bg-muted/50 text-muted-foreground">
                <tr>
                    <th class="p-2 pl-4 font-medium">Status</th>
                    <th class="p-2 text-right font-medium">Links</th>
                    <th class="p-2 pr-4 text-right font-medium">Share</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="row in rows"
                    :key="row.label"
                    class="border-t hover:bg-muted/30"
                >
                    <td class="p-2 pl-4">{{ row.label }}</td>
                    <td class="p-2 text-right tabular-nums">{{ row.count }}</td>
                    <td class="p-2 pr-4 text-right tabular-nums">
                        {{ row.share }}%
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
