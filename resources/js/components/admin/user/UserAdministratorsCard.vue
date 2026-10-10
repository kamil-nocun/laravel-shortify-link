<script setup lang="ts">
import { Link as InertiaLink } from '@inertiajs/vue3';
import { show } from '@/routes/admin/users';
import type { AdministratorOption } from '@/types';

defineProps<{ administrators: AdministratorOption[] }>();
</script>

<template>
    <div class="space-y-2 rounded-lg border p-4">
        <h2 class="font-medium">Assigned administrators</h2>
        <ul v-if="administrators.length > 0" class="text-sm">
            <li v-for="administrator in administrators" :key="administrator.id">
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
</template>
