<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import PackageOverview from '@/components/PackageOverview.vue';
import PaymentHistory from '@/components/PaymentHistory.vue';
import { show } from '@/routes/billing/package';
import type { PaymentRow } from '@/types';

defineProps<{
    current: InstanceType<typeof PackageOverview>['$props']['current'];
    canBuy: boolean;
    awaitingPayment: boolean;
    invoiceUrl: string | null;
    payments: PaymentRow[];
    checkoutStatus: string | null;
    packages: InstanceType<typeof PackageOverview>['$props']['packages'];
    errors: Record<string, string>;
}>();

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

        <PackageOverview
            :current="current"
            :can-buy="canBuy"
            :awaiting-payment="awaitingPayment"
            :invoice-url="invoiceUrl"
            :checkout-status="checkoutStatus"
            :packages="packages"
            :errors="errors"
        />

        <PaymentHistory :payments="payments" />
    </div>
</template>
