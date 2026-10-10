<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { billingPeriodLabels, formatPrice, paymentStatusLabels } from '@/types';
import type { PaymentRow } from '@/types';

defineProps<{ payments: PaymentRow[] }>();
</script>

<template>
    <div class="space-y-3">
        <h2 class="text-sm font-medium">Payment history</h2>
        <p v-if="payments.length === 0" class="text-sm text-muted-foreground">
            No payments yet.
        </p>
        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-left text-sm">
                <thead class="border-b text-muted-foreground">
                    <tr>
                        <th class="p-3">Date</th>
                        <th class="p-3">Package</th>
                        <th class="p-3">Amount</th>
                        <th class="p-3">Status</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="payment in payments" :key="payment.id" class="border-b last:border-0">
                        <td class="p-3">
                            {{ new Date(payment.issued_at).toLocaleDateString() }}
                        </td>
                        <td class="p-3">
                            {{ payment.package_name ?? '—' }}
                            <span v-if="payment.billing_period" class="text-muted-foreground">
                                · {{ billingPeriodLabels[payment.billing_period] }}
                            </span>
                        </td>
                        <td class="p-3">{{ formatPrice(payment.amount) }}</td>
                        <td class="p-3">
                            <Badge :variant="payment.status === 'paid' ? 'default' : 'outline'">{{
                                paymentStatusLabels[payment.status]
                            }}</Badge>
                        </td>
                        <td class="p-3">
                            <a
                                v-if="payment.invoice_url"
                                :href="payment.invoice_url"
                                target="_blank"
                                rel="noopener"
                                class="underline underline-offset-4"
                                >{{ payment.status === 'open' ? 'Pay' : 'View' }}</a
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
