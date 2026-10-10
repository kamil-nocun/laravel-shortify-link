<script setup lang="ts">
import { Head, Link as InertiaLink, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, index, store, verify } from '@/routes/domains';
import { show as billingPackage } from '@/routes/billing/package';

type DomainRow = {
    id: string;
    host: string;
    url: string;
    status: 'pending' | 'active' | 'disabled';
    txt_name: string;
    txt_value: string;
};

const props = defineProps<{
    domains: DomainRow[];
    quota: { used: number; limit: number | null };
    cnameTarget: string;
}>();

const form = useForm({ host: '' });

const noDomainsAllowed = computed(() => props.quota.limit === 0);
const limitReached = computed(
    () => props.quota.limit !== null && props.quota.used >= props.quota.limit,
);

const verifyError = ref<{ id: string; message: string } | null>(null);

function checkAgain(domain: DomainRow) {
    verifyError.value = null;
    router.post(
        verify(domain.id).url,
        {},
        {
            preserveScroll: true,
            onError: (errors) => {
                verifyError.value = { id: domain.id, message: errors.host };
            },
        },
    );
}

function submit() {
    form.post(store().url, { onSuccess: () => form.reset() });
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Domains', href: index() }],
    },
});
</script>

<template>
    <Head title="Domains" />

    <div class="space-y-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                title="Domains"
                description="Use your own domain for short links"
            />
            <span class="text-sm text-muted-foreground">
                {{
                    quota.limit === null
                        ? `${quota.used} domains, no limit`
                        : `${quota.used} of ${quota.limit} domains`
                }}
            </span>
        </div>

        <Alert v-if="noDomainsAllowed">
            <AlertDescription>
                Your package does not include custom domains.
                <InertiaLink
                    :href="billingPackage()"
                    class="font-medium underline"
                    >Choose a package</InertiaLink
                >
                to add one.
            </AlertDescription>
        </Alert>

        <form
            v-else-if="!limitReached"
            class="flex max-w-xl items-start gap-2"
            @submit.prevent="submit"
        >
            <div class="grid flex-1 gap-2">
                <Label for="host">Domain</Label>
                <Input
                    id="host"
                    v-model="form.host"
                    required
                    placeholder="links.example.com"
                />
                <InputError :message="form.errors.host" />
            </div>
            <Button type="submit" class="mt-6" :disabled="form.processing"
                >Add domain</Button
            >
        </form>
        <p v-else class="text-sm text-muted-foreground">
            Domain limit reached for your package.
        </p>

        <p v-if="domains.length === 0" class="text-sm text-muted-foreground">
            You have no domains yet.
        </p>

        <div v-for="domain in domains" :key="domain.id" class="rounded-lg border p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <a
                        v-if="domain.status === 'active'"
                        :href="domain.url"
                        target="_blank"
                        rel="noopener"
                        class="font-mono underline"
                        >{{ domain.host }}</a
                    >
                    <span v-else class="font-mono">{{ domain.host }}</span>
                    <Badge
                        :variant="
                            domain.status === 'active'
                                ? 'default'
                                : domain.status === 'disabled'
                                  ? 'destructive'
                                  : 'secondary'
                        "
                        >{{
                            domain.status === 'pending'
                                ? 'Pending verification'
                                : domain.status === 'active'
                                  ? 'Active'
                                  : 'Blocked by admin'
                        }}</Badge
                    >
                </div>
                <div class="flex gap-2">
                    <Button
                        v-if="domain.status === 'pending'"
                        variant="outline"
                        size="sm"
                        :aria-label="`Check ${domain.host} again`"
                        @click="checkAgain(domain)"
                        >Check again</Button
                    >
                    <Button
                        variant="destructive"
                        size="sm"
                        :aria-label="`Delete ${domain.host}`"
                        @click="router.delete(destroy(domain.id).url)"
                        >Delete</Button
                    >
                </div>
            </div>

            <div
                v-if="domain.status === 'pending'"
                class="mt-4 space-y-2 text-sm"
            >
                <p class="text-muted-foreground">
                    Add these DNS records at your DNS provider. Verification is
                    retried automatically for 72 hours.
                </p>
                <table class="w-full text-left font-mono text-xs">
                    <thead class="text-muted-foreground">
                        <tr>
                            <th class="py-1">Type</th>
                            <th class="py-1">Name</th>
                            <th class="py-1">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="py-1">TXT</td>
                            <td class="py-1">{{ domain.txt_name }}</td>
                            <td class="py-1 break-all">
                                {{ domain.txt_value }}
                            </td>
                        </tr>
                        <tr>
                            <td class="py-1">CNAME</td>
                            <td class="py-1">{{ domain.host }}</td>
                            <td class="py-1">{{ cnameTarget }}</td>
                        </tr>
                    </tbody>
                </table>
                <InputError
                    :message="
                        verifyError?.id === domain.id
                            ? verifyError.message
                            : undefined
                    "
                />
            </div>
        </div>
    </div>
</template>
