<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/admin/users/package';
import { billingPeriodLabels, formatPrice } from '@/types';
import type {
    BillingPeriod,
    PackageAssignment,
    PackageOption,
} from '@/types';

const props = defineProps<{
    userId: string;
    assignment: PackageAssignment | null;
    packages: PackageOption[];
    paymentState: 'awaiting_payment' | 'active' | 'expired' | null;
}>();

const form = useForm({
    package_id: props.assignment?.package_id ?? props.packages[0]?.id ?? '',
    billing_period: '' as BillingPeriod | '',
});

const extraErrors = computed(
    () => form.errors as Record<string, string | undefined>,
);

const availablePeriods = computed(
    () =>
        props.packages.find((pkg) => pkg.id === form.package_id)
            ?.available_periods ?? [],
);

watch(
    availablePeriods,
    (periods) => {
        if (!periods.includes(form.billing_period as BillingPeriod)) {
            form.billing_period = periods[0] ?? '';
        }
    },
    { immediate: true },
);

const submit = () =>
    form.put(update(props.userId).url, { preserveScroll: true });
</script>

<template>
    <div class="space-y-3 rounded-lg border p-4">
        <h2 class="text-sm font-medium">Package</h2>

        <template v-if="packages.length > 0">
            <p v-if="assignment" class="flex flex-wrap items-center gap-2 text-sm">
                {{ assignment.package_name }},
                {{ billingPeriodLabels[assignment.billing_period] }},
                {{ formatPrice(assignment.price) }}, until
                {{ new Date(assignment.expires_at).toLocaleString() }}
                <Badge :variant="assignment.active ? 'default' : 'destructive'">{{
                    assignment.active ? 'Active' : 'Expired'
                }}</Badge>
                <Badge v-if="assignment.package_withdrawn" variant="outline"
                    >Withdrawn</Badge
                >
            </p>
            <p v-else class="text-sm text-muted-foreground">
                No active package; the default package applies.
            </p>
            <Badge v-if="paymentState === 'awaiting_payment'" variant="outline"
                >Awaiting payment</Badge
            >
            <InputError :message="extraErrors.package" />
            <InputError :message="extraErrors.stripe" />

            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="package_id">Package</Label>
                    <select
                        id="package_id"
                        v-model="form.package_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">
                            {{ pkg.name }} ({{ pkg.link_limit }} links)
                        </option>
                    </select>
                    <InputError :message="form.errors.package_id" />
                </div>

                <fieldset class="grid gap-2">
                    <legend class="text-sm font-medium">Billing period</legend>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <label
                            v-for="period in availablePeriods"
                            :key="period"
                            class="flex items-center gap-1"
                        >
                            <input
                                v-model="form.billing_period"
                                type="radio"
                                name="billing_period"
                                :value="period"
                            />
                            {{ billingPeriodLabels[period] }}
                        </label>
                        <span
                            v-if="availablePeriods.length === 0"
                            class="text-muted-foreground"
                            >No priced periods</span
                        >
                    </div>
                    <InputError :message="form.errors.billing_period" />
                </fieldset>

                <div class="sm:col-span-2">
                    <Button
                        type="submit"
                        size="sm"
                        :disabled="form.processing || !form.billing_period"
                        
                        >Assign and send invoice</Button
                    >
                </div>
            </form>
        </template>
        <p v-else class="text-sm text-muted-foreground">
            No package is available for sale. Create a paid package with at
            least one price first.
        </p>
    </div>
</template>
