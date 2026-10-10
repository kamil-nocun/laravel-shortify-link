<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import UserAdministratorsCard from '@/components/admin/user/UserAdministratorsCard.vue';
import UserDomainsTable from '@/components/admin/user/UserDomainsTable.vue';
import UserHeader from '@/components/admin/user/UserHeader.vue';
import UserLinksTable from '@/components/admin/user/UserLinksTable.vue';
import UserPackageCard from '@/components/admin/user/UserPackageCard.vue';
import UserPermissionsCard from '@/components/admin/user/UserPermissionsCard.vue';
import UserStatsSection from '@/components/admin/user/UserStatsSection.vue';
import { dashboard } from '@/routes/admin';
import { index, show } from '@/routes/admin/users';
import type {
    AdminCounters,
    AdminDomainRow,
    AdministratorOption,
    AdminUser,
    AssignmentRow,
    DailyClicks,
    Link,
    PackageAssignment,
    PackageOption,
    Paginated,
    PaymentRow,
    PermissionGroup,
    StatsPeriodParams,
} from '@/types';

const props = defineProps<{
    user: AdminUser;
    permissionOverrides: { granted: string[]; revoked: string[] };
    rolePermissions: string[];
    permissionGroups: PermissionGroup[];
    roles: { id: string; name: string }[];
    assignedAdministrators: AdministratorOption[];
    packageAssignment: PackageAssignment | null;
    packages: PackageOption[];
    effectiveLimit: number | null;
    invoiceUrl: string | null;
    payments: Paginated<PaymentRow> | null;
    assignments: Paginated<AssignmentRow> | null;
    paymentState: 'awaiting_payment' | 'active' | 'expired' | null;
    links: Paginated<Link>;
    domains: AdminDomainRow[];
    domainLimit: number | null;
    period: StatsPeriodParams;
    granularity: 'day' | 'month';
    total: number;
    series: DailyClicks[];
    counters: AdminCounters;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Admin', href: dashboard() },
        { title: 'Users', href: index() },
        { title: props.user.name, href: show(props.user.id) },
    ],
});
</script>

<template>
    <Head :title="user.name" />

    <div class="space-y-6 p-4">
        <UserHeader :user="user" :effective-limit="effectiveLimit" />

        <UserAdministratorsCard
            v-if="user.can.assignAdministrators"
            :administrators="assignedAdministrators"
        />

        <UserPermissionsCard
            v-if="roles.length > 0"
            :user="user"
            :roles="roles"
            :permission-groups="permissionGroups"
            :role-permissions="rolePermissions"
            :permission-overrides="permissionOverrides"
        />

        <UserPackageCard
            :user-id="user.id"
            :assignment="packageAssignment"
            :packages="packages"
            :payment-state="paymentState"
            :invoice-url="invoiceUrl"
            :assignments="assignments"
            :payments="payments"
        />

        <UserDomainsTable
            v-if="user.can.manageDomains || user.can.disableDomains"
            :user="user"
            :domains="domains"
            :domain-limit="domainLimit"
        />

        <UserStatsSection
            :user-id="user.id"
            :period="period"
            :granularity="granularity"
            :total="total"
            :series="series"
            :counters="counters"
        />

        <UserLinksTable :user="user" :links="links" />
    </div>
</template>
