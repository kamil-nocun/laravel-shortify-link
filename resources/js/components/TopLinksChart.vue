<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import BreakdownChart from '@/components/BreakdownChart.vue';
import type { TopLink } from '@/types';

const props = defineProps<{
    links: TopLink[];
    href: (id: string) => NonNullable<InertiaLinkProps['href']>;
}>();

function urlOf(id: string): string {
    const target = props.href(id);

    return typeof target === 'string' ? target : target.url;
}
</script>

<template>
    <section class="rounded-lg border">
        <h2 class="px-4 py-3 font-medium">Top links</h2>
        <p
            v-if="links.length === 0"
            class="px-4 pb-4 text-sm text-muted-foreground"
        >
            No data
        </p>
        <div v-else class="px-4 pb-4">
            <BreakdownChart
                type="bar"
                :items="
                    links.map((link) => ({
                        label: link.title ?? link.code,
                        value: link.clicks,
                    }))
                "
                :urls="links.map((link) => urlOf(link.id))"
            />
        </div>
    </section>
</template>
