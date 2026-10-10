<script setup lang="ts">
import { Head, Link as InertiaLink, router } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import Heading from "@/components/Heading.vue";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
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
import { Input } from "@/components/ui/input";
import { create, destroy, edit, index, stats, toggle } from "@/routes/links";
import type { Link, LinkFilters, Paginated } from "@/types";

const props = defineProps<{
    links: Paginated<Link>;
    filters: LinkFilters;
    domains: { id: string; host: string }[];
    quota: { used: number; limit: number | null };
    packageNotice: {
        package_name: string;
        ends_at: string;
        default_package_name: string | null;
    } | null;
}>();

const limitReached = computed(
    () => props.quota.limit !== null && props.quota.used >= props.quota.limit,
);

const search = ref(props.filters.search ?? "");
const status = ref(props.filters.status ?? "");
const domain = ref(props.filters.domain ?? "");

const hasFilters = computed(
    () =>
        !!(
            props.filters.search ||
            props.filters.status ||
            props.filters.domain
        ),
);

function applyFilters(): void {
    const params = Object.fromEntries(
        Object.entries({
            search: search.value,
            status: status.value,
            domain: domain.value,
        }).filter(([, value]) => value !== ""),
    );

    router.get(index().url, params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

let searchTimer: ReturnType<typeof setTimeout> | undefined;

function onSearchInput(): void {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
}

function clearFilters(): void {
    search.value = "";
    status.value = "";
    domain.value = "";
    applyFilters();
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: "My links", href: index() }],
    },
});
</script>

<template>
    <Head title="My links" />

    <div class="space-y-6 p-4">
        <div class="flex items-center justify-between">
            <Heading title="My links" description="Manage your short links" />
            <div class="flex items-center gap-3">
                <span class="text-sm text-muted-foreground">
                    {{
                        quota.limit === null
                            ? `${quota.used} links, no limit`
                            : `${quota.used} of ${quota.limit} links`
                    }}
                </span>
                <Button v-if="limitReached" disabled title="Link limit reached"
                    >New link</Button
                >
                <Button v-else as-child>
                    <InertiaLink :href="create()">New link</InertiaLink>
                </Button>
            </div>
        </div>

        <Alert v-if="packageNotice">
            <AlertTitle>Package being withdrawn</AlertTitle>
            <AlertDescription>
                Package {{ packageNotice.package_name }} is being withdrawn.
                From
                {{ new Date(packageNotice.ends_at).toLocaleDateString() }}
                {{ packageNotice.default_package_name ?? "default" }} package
                applies.
            </AlertDescription>
        </Alert>

        <div class="flex flex-wrap gap-2">
            <Input
                v-model="search"
                type="search"
                placeholder="Search title, code or URL"
                class="max-w-xs"
                @input="onSearchInput"
            />
            <select
                v-model="status"
                aria-label="Status"
                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                @change="applyFilters"
            >
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="expired">Expired</option>
                <option value="disabled">Blocked by admin</option>
            </select>
            <select
                v-if="domains.length"
                v-model="domain"
                aria-label="Domain"
                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                @change="applyFilters"
            >
                <option value="">All domains</option>
                <option value="main">Main domain</option>
                <option v-for="d in domains" :key="d.id" :value="d.id">
                    {{ d.host }}
                </option>
            </select>
        </div>

        <div
            v-if="links.data.length === 0 && hasFilters"
            class="flex items-center gap-3 text-sm text-muted-foreground"
        >
            <p>No links match your filters.</p>
            <Button variant="outline" size="sm" @click="clearFilters"
                >Clear filters</Button
            >
        </div>

        <p
            v-else-if="links.data.length === 0"
            class="text-sm text-muted-foreground"
        >
            You have no links yet.
        </p>

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th class="p-3">Title</th>
                        <th class="p-3">Target URL</th>
                        <th class="p-3">Short URL</th>
                        <th class="p-3">Clicks</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Expires</th>
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
                        <td class="max-w-64 truncate p-3">
                            {{ link.original_url }}
                        </td>
                        <td class="p-3 font-mono">{{ link.short_url }}</td>
                        <td class="p-3">{{ link.clicks_count ?? 0 }}</td>
                        <td class="p-3">
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="link.is_active"
                                    :aria-label="`Toggle link ${link.code}`"
                                    :disabled="!!link.disabled_by_admin_at"
                                    class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                                    :class="
                                        link.is_active
                                            ? 'bg-primary'
                                            : 'bg-input'
                                    "
                                    @click="
                                        router.patch(
                                            toggle(link.id).url,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    "
                                >
                                    <span
                                        class="inline-block size-4 rounded-full bg-background shadow transition-transform"
                                        :class="
                                            link.is_active
                                                ? 'translate-x-4'
                                                : 'translate-x-0.5'
                                        "
                                    />
                                </button>
                                <Badge
                                    v-if="link.disabled_by_admin_at"
                                    variant="destructive"
                                    >Blocked by admin</Badge
                                >
                            </div>
                        </td>
                        <td class="p-3">
                            {{
                                link.expires_at
                                    ? new Date(link.expires_at).toLocaleString()
                                    : "—"
                            }}
                        </td>
                        <td class="space-x-2 p-3 text-right">
                            <Button variant="outline" size="sm" as-child>
                                <InertiaLink :href="stats(link.id)"
                                    >Stats</InertiaLink
                                >
                            </Button>
                            <Button variant="outline" size="sm" as-child>
                                <InertiaLink :href="edit(link.id)"
                                    >Edit</InertiaLink
                                >
                            </Button>
                            <Dialog>
                                <DialogTrigger as-child>
                                    <Button variant="destructive" size="sm"
                                        >Delete</Button
                                    >
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Delete link?</DialogTitle>
                                        <DialogDescription>
                                            The code {{ link.code }} will stop
                                            working. This cannot be undone.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <DialogFooter class="gap-2">
                                        <DialogClose as-child>
                                            <Button variant="secondary"
                                                >Cancel</Button
                                            >
                                        </DialogClose>
                                        <Button
                                            variant="destructive"
                                            @click="
                                                router.delete(
                                                    destroy(link.id).url,
                                                )
                                            "
                                            >Delete</Button
                                        >
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
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
