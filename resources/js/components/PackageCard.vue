<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { show as billingPackage } from '@/routes/billing/package';
import type { DashboardPackage } from '@/types';

defineProps<{
    package: DashboardPackage;
}>();
</script>

<template>
    <section class="space-y-4 rounded-lg border p-4">
        <div>
            <h2 class="font-medium">Your package</h2>
            <p class="text-2xl font-semibold">{{ package.package_name }}</p>
            <p v-if="package.expires_at" class="text-sm text-muted-foreground">
                Valid until
                {{ new Date(package.expires_at).toLocaleDateString() }}
            </p>
        </div>

        <div
            v-for="usage in [
                {
                    label: 'Links',
                    used: package.links_used,
                    limit: package.link_limit,
                },
                {
                    label: 'Domains',
                    used: package.domains_used,
                    limit: package.domain_limit,
                },
            ]"
            :key="usage.label"
            class="space-y-1"
        >
            <div class="flex justify-between text-sm">
                <span>{{ usage.label }}</span>
                <span class="tabular-nums text-muted-foreground">
                    {{ usage.used }} /
                    {{ usage.limit === null ? 'unlimited' : usage.limit }}
                </span>
            </div>
            <div
                v-if="usage.limit !== null && usage.limit > 0"
                class="h-2 overflow-hidden rounded-full bg-muted"
                role="progressbar"
                :aria-label="`${usage.label} used`"
                :aria-valuenow="usage.used"
                aria-valuemin="0"
                :aria-valuemax="usage.limit"
            >
                <div
                    class="h-full bg-primary"
                    :style="{
                        width: `${Math.min(100, (usage.used / usage.limit) * 100)}%`,
                    }"
                />
            </div>
        </div>

        <Link
            :href="billingPackage()"
            class="text-sm underline-offset-4 hover:underline"
        >
            Manage package
        </Link>
    </section>
</template>
