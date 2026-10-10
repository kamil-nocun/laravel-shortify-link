<script setup lang="ts">
import BreakdownChart from '@/components/BreakdownChart.vue';

defineProps<{
    active: number;
    disabled: number;
}>();
</script>

<template>
    <section class="rounded-lg border">
        <h2 class="px-4 py-3 font-medium">Links by status</h2>
        <div class="px-4 pb-4">
            <BreakdownChart
                type="doughnut"
                value-label="Links"
                :items="[
                    { label: 'Active', value: active },
                    { label: 'Disabled', value: disabled },
                ]"
            />
        </div>
    </section>
</template>
