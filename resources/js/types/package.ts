export type Package = {
    id: string;
    name: string;
    link_limit: number;
    price_one_time: number | null;
    price_monthly: number | null;
    price_quarterly: number | null;
    price_yearly: number | null;
    is_default: boolean;
    assignments_count?: number;
};

export type BillingPeriod = 'one_time' | 'monthly' | 'quarterly' | 'yearly';

export type PackageOption = {
    id: string;
    name: string;
    link_limit: number;
    available_periods: BillingPeriod[];
};

export type PackageAssignment = {
    package_id: string;
    package_name: string;
    billing_period: BillingPeriod;
    price: number;
    starts_at: string;
    expires_at: string;
    active: boolean;
    package_withdrawn: boolean;
};

export const billingPeriodLabels: Record<BillingPeriod, string> = {
    one_time: 'One-time',
    monthly: 'Monthly',
    quarterly: 'Quarterly',
    yearly: 'Yearly',
};

export function formatPrice(cents: number | null): string {
    return cents === null ? '—' : `${(cents / 100).toFixed(2)} PLN`;
}
