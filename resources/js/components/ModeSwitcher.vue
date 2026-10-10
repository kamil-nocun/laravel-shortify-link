<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutDashboard, ShieldCheck } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useViewMode } from '@/composables/useViewMode';
import { dashboard } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';

const page = usePage();
const { isAdminMode } = useViewMode();
</script>

<template>
    <div
        v-if="page.props.auth.isAdmin"
        class="flex items-center gap-1 rounded-lg border p-1"
    >
        <Button
            :variant="isAdminMode ? 'ghost' : 'default'"
            size="sm"
            as-child
        >
            <Link
                :href="dashboard()"
                :aria-current="isAdminMode ? undefined : 'page'"
            >
                <LayoutDashboard />
                <span class="hidden sm:inline">Client</span>
            </Link>
        </Button>
        <Button
            :variant="isAdminMode ? 'default' : 'ghost'"
            size="sm"
            as-child
        >
            <Link
                :href="adminDashboard()"
                :aria-current="isAdminMode ? 'page' : undefined"
            >
                <ShieldCheck />
                <span class="hidden sm:inline">Admin</span>
            </Link>
        </Button>
    </div>
</template>
