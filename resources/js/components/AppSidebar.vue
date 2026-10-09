<script setup lang="ts">
import { Link, usePage } from "@inertiajs/vue3";
import {
    BookOpen,
    CreditCard,
    FolderGit2,
    Link2,
    Package,
    Shield,
    Users,
} from "@lucide/vue";
import { computed } from "vue";
import AppLogo from "@/components/AppLogo.vue";
import NavFooter from "@/components/NavFooter.vue";
import NavMain from "@/components/NavMain.vue";
import NavUser from "@/components/NavUser.vue";
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from "@/components/ui/sidebar";
import { dashboard } from "@/routes";
import { index as administratorsIndex } from "@/routes/admin/administrators";
import { index as packagesIndex } from "@/routes/admin/packages";
import { show as billingPackage } from "@/routes/billing/package";
import { index as usersIndex } from "@/routes/admin/users";
import { index as linksIndex } from "@/routes/links";
import type { NavItem } from "@/types";

const page = usePage();

const baseNavItems: NavItem[] = [
    {
        title: "My links",
        href: linksIndex(),
        icon: Link2,
    },
];

const mainNavItems = computed<NavItem[]>(() => {
    const role = page.props.auth.user.role;

    return [
        ...(role === "super_admin"
            ? [
                  { title: "Admin", href: administratorsIndex(), icon: Shield },
                  { title: "Packages", href: packagesIndex(), icon: Package },
              ]
            : []),
        ...(role === "admin" || role === "super_admin"
            ? [{ title: "Users", href: usersIndex(), icon: Users }]
            : []),
        ...baseNavItems,
        ...(role !== "super_admin"
            ? [{ title: "My package", href: billingPackage(), icon: CreditCard }]
            : []),
    ];
});

const footerNavItems: NavItem[] = [
    {
        title: "Repository",
        href: "https://github.com/laravel/vue-starter-kit",
        icon: FolderGit2,
    },
    {
        title: "Documentation",
        href: "https://laravel.com/docs/starter-kits#vue",
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
