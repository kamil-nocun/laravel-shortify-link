<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { checkout, show } from '@/routes/billing/package';
import { billingPeriodLabels, formatPrice } from '@/types';
import type { BillingPeriod } from '@/types';

type SellablePackage = {
    id: string;
    name: string;
    link_limit: number;
    prices: Partial<Record<BillingPeriod, number>>;
};

const props = defineProps<{
    current: {
        package_name: string | null;
        link_limit: number | null;
        billing_period: BillingPeriod | null;
        expires_at: string | null;
    };
    awaitingPayment: boolean;
    checkoutStatus: string | null;
    packages: SellablePackage[];
    errors: Record<string, string>;
}>();

const chosen = reactive<Record<string, BillingPeriod>>(
    Object.fromEntries(
        props.packages.map((pkg) => [pkg.id, Object.keys(pkg.prices)[0] as BillingPeriod]),
    ),
);

const buy = (pkg: SellablePackage) =>
    router.post(checkout().url, {
        package_id: pkg.id,
        billing_period: chosen[pkg.id],
    });

defineOptions({
    layout: { breadcrumbs: [{ title: 'My package', href: show() }] },
});
</script>

<template>
    <Head title="My package" />

    <div class="space-y-6 p-4">
        <Heading
            title="My package"
            description="Your current package and the packages you can buy"
        />

        <Alert v-if="checkoutStatus === 'success'">
            <AlertTitle>Payment received</AlertTitle>
            <AlertDescription
                >Your package activates as soon as Stripe confirms the
                payment.</AlertDescription
            >
        </Alert>
        <Alert v-else-if="checkoutStatus === 'cancelled'">
            <AlertTitle>Checkout cancelled</AlertTitle>
            <AlertDescription>No payment was made.</AlertDescription>
        </Alert>
        <Alert v-if="awaitingPayment">
            <AlertTitle>Awaiting payment</AlertTitle>
            <AlertDescription
                >An invoice for your package is waiting to be paid.</AlertDescription
            >
        </Alert>

        <div class="rounded-lg border p-4">
            <p class="text-sm text-muted-foreground">Current package</p>
            <p class="text-lg font-medium">
                {{ current.package_name ?? '—' }}
                <Badge v-if="current.billing_period" class="ml-2">{{
                    billingPeriodLabels[current.billing_period]
                }}</Badge>
            </p>
            <p class="text-sm">
                Link limit:
                {{ current.link_limit === null ? 'no limit' : current.link_limit }}
                <template v-if="current.expires_at">
                    · valid until
                    {{ new Date(current.expires_at).toLocaleDateString() }}
                </template>
            </p>
        </div>

        <InputError :message="errors.package" />

        <p v-if="packages.length === 0" class="text-sm text-muted-foreground">
            No packages are available for purchase.
        </p>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="pkg in packages"
                :key="pkg.id"
                class="space-y-3 rounded-lg border p-4"
            >
                <p class="text-lg font-medium">{{ pkg.name }}</p>
                <p class="text-sm text-muted-foreground">
                    {{ pkg.link_limit }} links
                </p>
                <div class="space-y-1 text-sm">
                    <label
                        v-for="(price, period) in pkg.prices"
                        :key="period"
                        class="flex items-center gap-2"
                    >
                        <input
                            v-model="chosen[pkg.id]"
                            type="radio"
                            :name="`period-${pkg.id}`"
                            :value="period"
                        />
                        {{ billingPeriodLabels[period as BillingPeriod] }}:
                        {{ formatPrice(price ?? null) }}
                    </label>
                </div>
                <InputError :message="errors.billing_period" />
                <Button
                    size="sm"
                    :disabled="current.billing_period !== null || awaitingPayment"
                    @click="buy(pkg)"
                    >Buy</Button
                >
            </div>
        </div>
    </div>
</template>
