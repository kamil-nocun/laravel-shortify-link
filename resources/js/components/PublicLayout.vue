<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { dashboard, front_app, login, register } from '@/routes';
import { show } from '@/routes/content';

withDefaults(
    defineProps<{
        legalDocuments?: { title: string; slug: string }[];
        sectionLinks?: boolean;
    }>(),
    { legalDocuments: () => [], sectionLinks: false },
);

const page = usePage();
const name = computed(() => page.props.name);
const year = new Date().getFullYear();

const sections = [
    { label: 'Features', href: '#features' },
    { label: 'How it works', href: '#how-it-works' },
    { label: 'Pricing', href: '#pricing' },
    { label: 'FAQ', href: '#faq' },
];
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header
            class="sticky top-0 z-20 border-b bg-background/90 backdrop-blur"
        >
            <div
                class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-4 px-4"
            >
                <Link :href="front_app()" class="flex items-center">
                    <AppLogo />
                </Link>

                <nav
                    v-if="sectionLinks"
                    class="hidden items-center gap-6 text-sm text-muted-foreground md:flex"
                >
                    <a
                        v-for="section in sections"
                        :key="section.href"
                        :href="section.href"
                        class="hover:text-foreground"
                        >{{ section.label }}</a
                    >
                </nav>

                <div class="flex items-center gap-2">
                    <Button v-if="page.props.auth.user" as-child size="sm">
                        <Link :href="dashboard()">Dashboard</Link>
                    </Button>
                    <template v-else>
                        <Button as-child variant="ghost" size="sm">
                            <Link :href="login()">Log in</Link>
                        </Button>
                        <Button as-child size="sm">
                            <Link :href="register()">Sign up</Link>
                        </Button>
                    </template>
                </div>
            </div>

            <nav
                v-if="sectionLinks"
                class="flex gap-6 overflow-x-auto border-t px-4 py-2 text-sm text-muted-foreground md:hidden"
                aria-label="Sections"
            >
                <a
                    v-for="section in sections"
                    :key="section.href"
                    :href="section.href"
                    class="whitespace-nowrap hover:text-foreground"
                    >{{ section.label }}</a
                >
            </nav>
        </header>

        <main class="flex-1">
            <slot />
        </main>

        <footer class="border-t">
            <div
                class="mx-auto flex w-full max-w-6xl flex-col gap-4 px-4 py-8 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between"
            >
                <p>&copy; {{ year }} {{ name }}</p>
                <nav
                    v-if="legalDocuments.length"
                    class="flex flex-wrap gap-x-6 gap-y-2"
                    aria-label="Legal documents"
                >
                    <Link
                        v-for="document in legalDocuments"
                        :key="document.slug"
                        :href="show(document.slug)"
                        class="hover:text-foreground"
                        >{{ document.title }}</Link
                    >
                </nav>
            </div>
        </footer>
    </div>
</template>
