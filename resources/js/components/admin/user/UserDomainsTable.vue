<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    destroy as destroyDomain,
    disable as disableDomain,
    enable as enableDomain,
} from '@/routes/admin/domains';
import { store as storeDomain } from '@/routes/admin/users/domains';
import type { AdminDomainRow as DomainRow, AdminUser } from '@/types';

const props = defineProps<{
    user: AdminUser;
    domains: DomainRow[];
    domainLimit: number | null;
}>();

const domainForm = useForm({ host: '' });

const addDomain = () =>
    domainForm.post(storeDomain(props.user.id).url, {
        preserveScroll: true,
        onSuccess: () => domainForm.reset(),
    });

const toggleDomain = (domain: DomainRow) =>
    domain.status === 'disabled'
        ? router.delete(enableDomain(domain.id).url, { preserveScroll: true })
        : router.patch(
              disableDomain(domain.id).url,
              {},
              { preserveScroll: true },
          );

const deleteDomain = (domain: DomainRow) =>
    router.delete(destroyDomain(domain.id).url, { preserveScroll: true });
</script>

<template>
    <div class="space-y-3 rounded-lg border p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-medium">Domains</h2>
            <span class="text-sm text-muted-foreground tabular-nums">
                {{ domains.length }} /
                {{ domainLimit ?? 'unlimited' }}
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
            <Button type="submit" class="mt-6" :disabled="domainForm.processing"
                >Add domain</Button
            >
        </form>
        <p
            v-else-if="user.can.manageDomains"
            class="text-sm text-muted-foreground"
        >
            Domain limit reached for the user's package.
        </p>

        <p v-if="domains.length === 0" class="text-sm text-muted-foreground">
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
                            domain.status === 'disabled'
                                ? `Disabled by ${domain.disabled_by_name ?? 'admin'}`
                                : domain.status === 'active'
                                  ? 'Active'
                                  : 'Pending'
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
                        {{
                            domain.status === 'disabled' ? 'Enable' : 'Disable'
                        }}
                    </Button>
                    <Button
                        v-if="user.can.manageDomains"
                        variant="outline"
                        size="sm"
                        :disabled="domain.links_count > 0"
                        :title="
                            domain.links_count > 0 ? 'Used by links' : undefined
                        "
                        :aria-label="`Delete domain ${domain.host}`"
                        @click="deleteDomain(domain)"
                    >
                        Delete
                    </Button>
                </div>
            </li>
        </ul>
    </div>
</template>
