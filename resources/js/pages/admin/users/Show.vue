<script setup lang="ts">
import {
    Head,
    Link as InertiaLink,
    router,
    setLayoutProps,
    useForm,
    usePage,
} from "@inertiajs/vue3";
import { computed, ref, useTemplateRef } from "vue";
import ClicksChart from "@/components/ClicksChart.vue";
import PackageHistory from "@/components/PackageHistory.vue";
import PaymentHistory from "@/components/PaymentHistory.vue";
import UserPackageCard from "@/components/UserPackageCard.vue";
import Heading from "@/components/Heading.vue";
import { Badge } from "@/components/ui/badge";
import InputError from "@/components/InputError.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { dashboard } from "@/routes/admin";
import {
    destroy as destroyDomain,
    disable as disableDomain,
    enable as enableDomain,
} from "@/routes/admin/domains";
import { store as storeDomain } from "@/routes/admin/users/domains";
import { disable, enable, stats as linkStats } from "@/routes/admin/links";
import {
    block,
    destroy,
    index,
    role as updateRole,
    show,
    unblock,
} from "@/routes/admin/users";
import type {
    AdminCounters,
    AdministratorOption,
    AdminUser,
    DailyClicks,
    Link,
    PackageAssignment,
    PackageOption,
    AssignmentRow,
    PaymentRow,
    Paginated,
    StatsPeriodParams,
} from "@/types";

const props = defineProps<{
    user: AdminUser;
    roleOptions: { client: string; administrator: string };
    assignedAdministrators: AdministratorOption[];
    packageAssignment: PackageAssignment | null;
    packages: PackageOption[];
    effectiveLimit: number | null;
    invoiceUrl: string | null;
    payments: Paginated<PaymentRow> | null;
    assignments: Paginated<AssignmentRow> | null;
    paymentState: "awaiting_payment" | "active" | "expired" | null;
    links: Paginated<Link>;
    domains: DomainRow[];
    domainLimit: number | null;
    period: StatsPeriodParams;
    granularity: "day" | "month";
    total: number;
    series: DailyClicks[];
    counters: AdminCounters;
}>();

type DomainRow = {
    id: string;
    host: string;
    status: "pending" | "active" | "disabled";
    disabled_by_name: string | null;
    links_count: number;
};

const domainForm = useForm({ host: "" });

const addDomain = () =>
    domainForm.post(storeDomain(props.user.id).url, {
        preserveScroll: true,
        onSuccess: () => domainForm.reset(),
    });

const toggleDomain = (domain: DomainRow) =>
    domain.status === "disabled"
        ? router.delete(enableDomain(domain.id).url, { preserveScroll: true })
        : router.patch(disableDomain(domain.id).url, {}, { preserveScroll: true });

const ranges = [7, 30, 90];

const cards = computed(() => [
    { label: "Links", value: props.counters.links },
    {
        label: "Active / disabled links",
        value: `${props.counters.activeLinks} / ${props.counters.disabledLinks}`,
    },
    { label: "Clicks", value: props.counters.clicks },
]);

const setPeriod = (range: number) =>
    router.get(
        show(props.user.id).url,
        { period: `${range}d` },
        { preserveScroll: true },
    );

const toggleBlock = () =>
    props.user.blocked_at
        ? router.delete(unblock(props.user.id).url, { preserveScroll: true })
        : router.patch(block(props.user.id).url, {}, { preserveScroll: true });

const isAdministrator = computed(
    () => props.user.role_id === props.roleOptions.administrator,
);
const targetRoleLabel = computed(() =>
    isAdministrator.value ? "Client" : "Administrator",
);

const isRoleOpen = ref(false);

const changeRole = () =>
    router.patch(
        updateRole(props.user.id).url,
        {
            role_id: isAdministrator.value
                ? props.roleOptions.client
                : props.roleOptions.administrator,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                isRoleOpen.value = false;
            },
        },
    );

const isDeleteOpen = ref(false);
const isDeleting = ref(false);
const cancelButton = useTemplateRef<{ $el: HTMLElement }>("cancelButton");

const focusCancel = (event: Event) => {
    event.preventDefault();
    cancelButton.value?.$el.focus();
};

const deleteUser = () =>
    router.delete(destroy(props.user.id).url, {
        onStart: () => {
            isDeleting.value = true;
        },
        onFinish: () => {
            isDeleting.value = false;
            isDeleteOpen.value = false;
        },
    });

const toggleLink = (link: Link) =>
    link.disabled_by_admin_at
        ? router.delete(enable(link.id).url, { preserveScroll: true })
        : router.patch(disable(link.id).url, {}, { preserveScroll: true });

const isSuperAdmin = computed(
    () => usePage().props.auth.isSuperAdmin,
);

const linkStatus = (link: Link) =>
    link.disabled_by_admin_at
        ? "Disabled by"
        : link.is_active
          ? "Active"
          : "Disabled by owner";

setLayoutProps({
    breadcrumbs: [
        { title: "Admin", href: dashboard() },
        { title: "Users", href: index() },
        { title: props.user.name, href: show(props.user.id) },
    ],
});
</script>

<template>
    <Head :title="user.name" />

    <div class="space-y-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <Heading :title="user.name" :description="user.email" />
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <Badge variant="outline">{{ user.role }}</Badge>
                    <Badge
                        :variant="user.blocked_at ? 'destructive' : 'secondary'"
                    >
                        {{ user.blocked_at ? "Blocked" : "Active" }}
                        <template v-if="user.blocked_by_name">
                            by
                            <InertiaLink
                                v-if="isSuperAdmin && user.blocked_by_id"
                                :href="show(user.blocked_by_id)"
                                class="underline-offset-4 hover:underline"
                            >
                                {{ user.blocked_by_name }}
                            </InertiaLink>
                            <template v-else>{{
                                user.blocked_by_name
                            }}</template>
                        </template>
                    </Badge>
                    <span class="text-muted-foreground">
                        Registered
                        {{ new Date(user.created_at).toLocaleDateString() }}
                    </span>
                    <span class="text-muted-foreground">
                        Link limit:
                        {{ effectiveLimit === null ? 'no limit' : effectiveLimit }}
                    </span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Dialog v-if="user.can.changeRole" v-model:open="isRoleOpen">
                    <DialogTrigger as-child>
                        <Button variant="outline" size="sm">
                            Change role to {{ targetRoleLabel }}
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Change role?</DialogTitle>
                            <DialogDescription>
                                {{ user.name }} will become
                                {{ targetRoleLabel }}.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>
                            <Button @click="changeRole"
                                >Change role to
                                {{ targetRoleLabel }}</Button
                            >
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
                <Button
                    v-if="user.can.block"
                    variant="outline"
                    size="sm"
                    @click="toggleBlock"
                >
                    {{ user.blocked_at ? "Unblock" : "Block" }}
                </Button>
                <Dialog v-if="user.can.delete" v-model:open="isDeleteOpen">
                    <DialogTrigger as-child>
                        <Button variant="destructive" size="sm">Delete</Button>
                    </DialogTrigger>
                    <DialogContent @open-auto-focus="focusCancel">
                        <DialogHeader>
                            <DialogTitle>Delete user?</DialogTitle>
                            <DialogDescription>
                                {{ user.name }} and all their links and stats
                                will be deleted. This cannot be undone.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button ref="cancelButton" variant="secondary"
                                    >Cancel</Button
                                >
                            </DialogClose>
                            <Button
                                variant="destructive"
                                :disabled="isDeleting"
                                @click="deleteUser"
                                >Delete</Button
                            >
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </div>

        <div v-if="user.can.assignAdministrators" class="space-y-2 rounded-lg border p-4">
            <h2 class="font-medium">Assigned administrators</h2>
            <ul v-if="assignedAdministrators.length > 0" class="text-sm">
                <li
                    v-for="administrator in assignedAdministrators"
                    :key="administrator.id"
                >
                    <InertiaLink
                        :href="show(administrator.id)"
                        class="underline-offset-4 hover:underline"
                    >
                        {{ administrator.name }}
                    </InertiaLink>
                    <span class="text-muted-foreground">
                        {{ administrator.email }}
                    </span>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                No administrators assigned.
            </p>
        </div>

        <UserPackageCard
            :user-id="user.id"
            :assignment="packageAssignment"
            :packages="packages"
            :payment-state="paymentState"
        />

        <p v-if="invoiceUrl" class="flex flex-wrap items-center gap-2 text-sm">
            <Badge variant="outline">Awaiting payment</Badge>
            <a
                :href="invoiceUrl"
                target="_blank"
                rel="noopener"
                class="underline underline-offset-4"
                >Open Stripe payment page</a
            >
        </p>

        <PackageHistory v-if="assignments" :assignments="assignments" />

        <PaymentHistory v-if="payments" :payments="payments" />

        <div v-if="user.can.manageDomains" class="space-y-3 rounded-lg border p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-medium">Domains</h2>
                <span class="text-sm text-muted-foreground tabular-nums">
                    {{ domains.length }} /
                    {{ domainLimit ?? "unlimited" }}
                </span>
            </div>

            <form
                v-if="user.can.createDomain"
                class="flex max-w-xl items-start gap-2"
                @submit.prevent="addDomain"
            >
                <div class="grid flex-1 gap-2">
                    <Label for="domain-host">Domain</Label>
                    <Input
                        id="domain-host"
                        v-model="domainForm.host"
                        required
                        placeholder="links.example.com"
                    />
                    <InputError :message="domainForm.errors.host" />
                </div>
                <Button
                    type="submit"
                    class="mt-6"
                    :disabled="domainForm.processing"
                    >Add domain</Button
                >
            </form>
            <p v-else class="text-sm text-muted-foreground">
                Domain limit reached for the user's package.
            </p>

            <p
                v-if="domains.length === 0"
                class="text-sm text-muted-foreground"
            >
                No domains.
            </p>
            <ul v-else class="divide-y text-sm">
                <li
                    v-for="domain in domains"
                    :key="domain.id"
                    class="flex flex-wrap items-center justify-between gap-2 py-2"
                >
                    <div class="flex items-center gap-2">
                        <span class="font-mono">{{ domain.host }}</span>
                        <Badge
                            :variant="
                                domain.status === 'active'
                                    ? 'default'
                                    : domain.status === 'disabled'
                                      ? 'destructive'
                                      : 'secondary'
                            "
                        >
                            {{
                                domain.status === "disabled"
                                    ? `Disabled by ${domain.disabled_by_name ?? "admin"}`
                                    : domain.status === "active"
                                      ? "Active"
                                      : "Pending"
                            }}
                        </Badge>
                    </div>
                    <div class="space-x-2">
                        <Button
                            v-if="user.can.disableDomains"
                            variant="outline"
                            size="sm"
                            :aria-label="`${domain.status === 'disabled' ? 'Enable' : 'Disable'} domain ${domain.host}`"
                            @click="toggleDomain(domain)"
                        >
                            {{ domain.status === "disabled" ? "Enable" : "Disable" }}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="domain.links_count > 0"
                            :title="
                                domain.links_count > 0
                                    ? 'Used by links'
                                    : undefined
                            "
                            :aria-label="`Delete domain ${domain.host}`"
                            @click="
                                router.delete(destroyDomain(domain.id).url, {
                                    preserveScroll: true,
                                })
                            "
                        >
                            Delete
                        </Button>
                    </div>
                </li>
            </ul>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-medium">Statistics</h2>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-for="range in ranges"
                    :key="range"
                    :variant="
                        `${range}d` === period.period ? 'default' : 'outline'
                    "
                    size="sm"
                    :aria-pressed="`${range}d` === period.period"
                    @click="setPeriod(range)"
                >
                    {{ range }} days
                </Button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div
                v-for="card in cards"
                :key="card.label"
                class="rounded-lg border p-4"
            >
                <p class="text-sm text-muted-foreground">{{ card.label }}</p>
                <p class="text-2xl font-semibold tabular-nums">
                    {{ card.value }}
                </p>
            </div>
        </div>

        <p v-if="total === 0" class="text-sm text-muted-foreground">
            No clicks in this period yet.
        </p>
        <ClicksChart v-else :series="series" :granularity="granularity" />

        <div class="flex items-center justify-between">
            <h2 class="font-medium">Links</h2>
        </div>

        <p v-if="links.data.length === 0" class="text-sm text-muted-foreground">
            No data
        </p>

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th class="p-3">Title</th>
                        <th class="p-3">Code</th>
                        <th class="p-3">Clicks</th>
                        <th class="p-3">Status</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="link in links.data"
                        :key="link.id"
                        class="border-t"
                    >
                        <td class="p-3">{{ link.title ?? "—" }}</td>
                        <td class="p-3 font-mono">{{ link.code }}</td>
                        <td class="p-3 tabular-nums">
                            {{ link.clicks_count ?? 0 }}
                        </td>
                        <td class="p-3">
                            <Badge
                                :variant="
                                    link.disabled_by_admin_at
                                        ? 'destructive'
                                        : link.is_active
                                          ? 'secondary'
                                          : 'outline'
                                "
                            >
                                {{ linkStatus(link) }}
                                <template v-if="link.disabled_by_admin_at">
                                    <InertiaLink
                                        v-if="isSuperAdmin && link.disabled_by"
                                        :href="show(link.disabled_by)"
                                        class="underline-offset-4 hover:underline"
                                    >
                                        {{ link.disabled_by_name }}
                                    </InertiaLink>
                                    <template v-else>{{
                                        link.disabled_by_name ?? "admin"
                                    }}</template>
                                </template>
                            </Badge>
                        </td>
                        <td class="space-x-2 p-3 text-right whitespace-nowrap">
                            <Button
                                v-if="user.can.disableLinks"
                                variant="outline"
                                size="sm"
                                :aria-label="`${link.disabled_by_admin_at ? 'Enable' : 'Disable'} link ${link.code}`"
                                @click="toggleLink(link)"
                            >
                                {{
                                    link.disabled_by_admin_at
                                        ? "Enable"
                                        : "Disable"
                                }}
                            </Button>
                            <Button variant="outline" size="sm" as-child>
                                <InertiaLink
                                    :href="linkStats(link.id)"
                                    :aria-label="`Stats of link ${link.code}`"
                                    >Stats</InertiaLink
                                >
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex gap-2">
            <Button
                v-if="links.prev_page_url"
                variant="outline"
                size="sm"
                as-child
            >
                <InertiaLink :href="links.prev_page_url">Previous</InertiaLink>
            </Button>
            <Button
                v-if="links.next_page_url"
                variant="outline"
                size="sm"
                as-child
            >
                <InertiaLink :href="links.next_page_url">Next</InertiaLink>
            </Button>
        </div>
    </div>
</template>
