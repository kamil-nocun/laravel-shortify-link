<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutDashboard, ShieldCheck } from '@lucide/vue';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useViewMode } from '@/composables/useViewMode';
import { dashboard } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';

const page = usePage();
const { isAdminMode } = useViewMode();
</script>

<template>
    <SidebarMenu v-if="page.props.auth.isAdmin">
        <SidebarMenuItem>
            <SidebarMenuButton
                as-child
                tooltip="Client view"
                :is-active="!isAdminMode"
            >
                <Link
                    :href="dashboard()"
                    :aria-current="isAdminMode ? undefined : 'page'"
                >
                    <LayoutDashboard />
                    <span>Client</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
        <SidebarMenuItem>
            <SidebarMenuButton
                as-child
                tooltip="Admin view"
                :is-active="isAdminMode"
            >
                <Link
                    :href="adminDashboard()"
                    :aria-current="isAdminMode ? 'page' : undefined"
                >
                    <ShieldCheck />
                    <span>Admin</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
