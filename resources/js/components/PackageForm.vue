<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { billingPeriodLabels } from '@/types';
import type { BillingPeriod, Package } from '@/types';

const props = defineProps<{
    package?: Package;
    submitLabel: string;
    action: (form: ReturnType<typeof usePackageForm>) => void;
}>();

const periods = Object.keys(billingPeriodLabels) as BillingPeriod[];

function toInput(cents: number | null | undefined): string {
    return cents === null || cents === undefined ? '' : (cents / 100).toFixed(2);
}

function toCents(value: string): number | null {
    return value === '' ? null : Math.round(Number(value) * 100);
}

function usePackageForm() {
    return useForm({
        name: props.package?.name ?? '',
        link_limit: props.package?.link_limit ?? 10,
        domain_limit: props.package?.domain_limit ?? 0,
        price_one_time: toInput(props.package?.price_one_time),
        price_monthly: toInput(props.package?.price_monthly),
        price_quarterly: toInput(props.package?.price_quarterly),
        price_yearly: toInput(props.package?.price_yearly),
        is_default: props.package?.is_default ?? false,
    }).transform((data) => ({
        ...data,
        price_one_time: toCents(data.price_one_time),
        price_monthly: toCents(data.price_monthly),
        price_quarterly: toCents(data.price_quarterly),
        price_yearly: toCents(data.price_yearly),
    }));
}

const form = usePackageForm();
</script>

<template>
    <form class="space-y-6" @submit.prevent="action(form)">
        <div class="grid gap-2">
            <Label for="name">Name</Label>
            <Input id="name" v-model="form.name" required />
            <InputError :message="form.errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="link_limit">Link limit</Label>
            <Input
                id="link_limit"
                v-model.number="form.link_limit"
                type="number"
                min="0"
                required
            />
            <InputError :message="form.errors.link_limit" />
        </div>

        <div class="grid gap-2">
            <Label for="domain_limit">Domain limit</Label>
            <Input
                id="domain_limit"
                v-model.number="form.domain_limit"
                type="number"
                min="0"
                required
            />
            <InputError :message="form.errors.domain_limit" />
        </div>

        <fieldset class="grid gap-4 sm:grid-cols-2">
            <legend class="mb-2 text-sm text-muted-foreground">
                Prices in PLN; leave empty to make a period unavailable
            </legend>
            <div v-for="period in periods" :key="period" class="grid gap-2">
                <Label :for="`price_${period}`">{{
                    billingPeriodLabels[period]
                }}</Label>
                <Input
                    :id="`price_${period}`"
                    v-model="form[`price_${period}`]"
                    type="number"
                    min="0"
                    step="0.01"
                />
                <InputError :message="form.errors[`price_${period}`]" />
            </div>
        </fieldset>

        <div class="flex items-center gap-2">
            <Checkbox
                id="is_default"
                :model-value="form.is_default"
                @update:model-value="form.is_default = $event === true"
            />
            <Label for="is_default"
                >Default package (used when a user has no active
                assignment)</Label
            >
        </div>

        <Button type="submit" :disabled="form.processing">{{
            submitLabel
        }}</Button>
    </form>
</template>
