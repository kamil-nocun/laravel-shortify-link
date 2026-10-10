<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import BreakdownChart from '@/components/BreakdownChart.vue';
import type { TopLink } from '@/types';

const props = defineProps<{
    links: TopLink[];
    asTable?: boolean;
    href: (id: string) => NonNullable<InertiaLinkProps['href']>;
}>();

function urlOf(id: string): string {
    const target = props.href(id);

    return typeof target === 'string' ? target : target.url;
}
</script>

<template>
    <section class="overflow-hidden rounded-lg border">
        <h2 class="px-4 py-3 font-medium">Top links</h2>
        <p
            v-if="links.length === 0"
            class="px-4 pb-4 text-sm text-muted-foreground"
        >
            No data
        </p>
        <table v-else-if="asTable" class="w-full text-left text-sm">
            <thead class="bg-muted/50 text-muted-foreground">
                <tr>
                    <th class="p-3">Link</th>
                    <th class="p-3 text-right">Clicks</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="link in links" :key="link.id" class="border-t">
                    <td class="p-3">
                        <Link
                            :href="href(link.id)"
                            class="underline-offset-4 hover:underline"
                        >
                            {{ link.title ?? link.code }}
                        </Link>
                    </td>
                    <td class="p-3 text-right tabular-nums">
                        {{ link.clicks }}
                    </td>
                </tr>
            </tbody>
        </table>
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
