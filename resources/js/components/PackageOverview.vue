<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cancel, checkout } from '@/routes/billing/package';
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
        cancellable: boolean;
        cancels: boolean;
        one_off: boolean;
        can_buy: boolean;
        next_package_name: string | null;
        next_starts_at: string | null;
    };
    awaitingPayment: boolean;
    invoiceUrl?: string | null;
    checkoutStatus?: string | null;
    packages: SellablePackage[];
    errors?: Record<string, string>;
    readonly?: boolean;
}>();

const chosen = reactive<Record<string, BillingPeriod>>(
    Object.fromEntries(
        props.packages.map((pkg) => [pkg.id, Object.keys(pkg.prices)[0] as BillingPeriod]),
    ),
);

const cancelSubscription = () => {
    if (confirm(props.current.one_off ? 'Cancel your package? It stays active until it expires and is not refunded.' : 'Cancel your subscription? The package stays active until the end of the paid period.')) {
        router.delete(cancel().url);
    }
};

const buy = (pkg: SellablePackage) =>
    router.post(checkout().url, {
        package_id: pkg.id,
        billing_period: chosen[pkg.id],
    });
</script>

<template>
    <div class="space-y-6">
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
                >An invoice for your package is waiting to be paid.
                <a
                    v-if="invoiceUrl"
                    :href="invoiceUrl"
                    target="_blank"
                    rel="noopener"
                    class="font-medium underline underline-offset-4"
                    >Pay invoice</a
                ></AlertDescription
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
            <p v-if="current.cancels" class="text-sm text-muted-foreground">
                Package cancelled — it will not renew.
            </p>
            <p v-if="current.next_package_name" class="text-sm">
                Next: {{ current.next_package_name }} · starts
                {{ new Date(current.next_starts_at as string).toLocaleDateString() }}
            </p>
            <Button
                v-if="current.cancellable && !readonly"
                variant="destructive"
                size="sm"
                class="mt-3"
                @click="cancelSubscription"
                >{{ current.one_off ? 'Cancel package' : 'Cancel subscription' }}</Button
            >
        </div>

        <InputError :message="errors?.package" />

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
                <InputError :message="errors?.billing_period" />
                <Button
                    size="sm"
                    :disabled="readonly || !current.can_buy || awaitingPayment"
                    @click="buy(pkg)"
                    >Buy</Button
                >
            </div>
        </div>
    </div>
</template>
