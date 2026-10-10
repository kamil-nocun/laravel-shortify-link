<script setup lang="ts">
import { Link, usePage } from "@inertiajs/vue3";
import {
    BookOpen,
    CreditCard,
    Globe,
    FolderGit2,
    Link2,
    Package,
    Shield,
    ShieldCheck,
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
import { usePermissions } from "@/composables/usePermissions";
import { dashboard } from "@/routes";
import { index as administratorsIndex } from "@/routes/admin/administrators";
import { index as packagesIndex } from "@/routes/admin/packages";
import { index as rolesIndex } from "@/routes/admin/roles";
import { show as billingPackage } from "@/routes/billing/package";
import { index as usersIndex } from "@/routes/admin/users";
import { index as domainsIndex } from "@/routes/domains";
import { index as linksIndex } from "@/routes/links";
import type { NavItem } from "@/types";

const page = usePage();
const { can } = usePermissions();

const mainNavItems = computed<NavItem[]>(() =>
    [
        {
            title: "Admin",
            href: administratorsIndex(),
            icon: Shield,
            visible: can("administrators.manage"),
        },
        {
            title: "Packages",
            href: packagesIndex(),
            icon: Package,
            visible: can("packages.manage"),
        },
        {
            title: "Roles",
            href: rolesIndex(),
            icon: ShieldCheck,
            visible: can("roles.manage"),
        },
        {
            title: "Users",
            href: usersIndex(),
            icon: Users,
            visible: can("users.view"),
        },
        {
            title: "My links",
            href: linksIndex(),
            icon: Link2,
            visible: can("links.manage"),
        },
        {
            title: "My domains",
            href: domainsIndex(),
            icon: Globe,
            visible: can("domains.manage"),
        },
        {
            title: "My package",
            href: billingPackage(),
            icon: CreditCard,
            visible: can("billing.manage") && !page.props.auth.isSuperAdmin,
        },
    ]
        .filter((item) => item.visible)
        .map(({ visible, ...item }) => item),
);

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
