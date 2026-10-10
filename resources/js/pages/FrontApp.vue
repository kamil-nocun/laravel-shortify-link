<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    ChartColumn,
    Check,
    Download,
    Globe,
    Link2,
    QrCode,
    ShieldCheck,
    Timer,
    Trash2,
} from '@lucide/vue';
import PublicLayout from '@/components/PublicLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard, register } from '@/routes';
import { formatPrice } from '@/types';
import type { BillingPeriod } from '@/types';

type PublicPackage = {
    id: string;
    name: string;
    link_limit: number;
    domain_limit: number;
    allows_custom_alias: boolean;
    is_default: boolean;
    price: number | null;
    period: BillingPeriod | null;
};

defineProps<{
    packages: PublicPackage[];
    legalDocuments: { title: string; slug: string }[];
}>();

const page = usePage();

const features = [
    {
        icon: Link2,
        title: 'Short links, your way',
        text: 'Turn any URL into a short link and, on selected plans, pick a custom alias that is easy to read and remember.',
    },
    {
        icon: Globe,
        title: 'Your own domains',
        text: 'Serve your links from your own domain, verified with a single DNS record.',
    },
    {
        icon: QrCode,
        title: 'QR codes',
        text: 'Download a QR code for every link and see how many scans it brings.',
    },
    {
        icon: ChartColumn,
        title: 'Click analytics',
        text: 'Daily clicks, countries, browsers, systems and devices, with QR scans told apart from link clicks.',
    },
    {
        icon: Timer,
        title: 'Full control',
        text: 'Give a link an expiry date or switch it off at any time and bring it back later.',
    },
    {
        icon: ShieldCheck,
        title: 'Privacy by design',
        text: 'Visitor IP addresses and user agents are never stored, and old clicks are kept only as daily summaries.',
    },
];

const steps = [
    {
        title: 'Paste your URL',
        text: 'Create a short link in seconds and choose its alias and domain.',
    },
    {
        title: 'Share it',
        text: 'Send the link or print the QR code wherever your audience is.',
    },
    {
        title: 'Track the results',
        text: 'Watch clicks, countries and devices and tune what works.',
    },
];

const privacyPoints = [
    { icon: ShieldCheck, text: 'No visitor IP addresses stored' },
    { icon: Download, text: 'Download all your data as CSV files' },
    { icon: Trash2, text: 'Delete your account and data at any time' },
];

const faqs = [
    {
        question: 'Is it free?',
        answer: 'Yes. Every account starts on the free package. Paid packages raise the limits and unlock custom domains and aliases.',
    },
    {
        question: 'Can I use my own domain?',
        answer: 'Yes, on packages that include custom domains. Add the domain, create the DNS record we show you and verify it.',
    },
    {
        question: 'Do you track my visitors?',
        answer: 'We record only the coarse location (country), browser, operating system and device type of each click. The visitor IP address is used once to find the country and is never stored, and finished days are kept as summaries.',
    },
    {
        question: 'Can I get a QR code for a link?',
        answer: 'Yes. Every link has a QR code you can download, and the statistics show how many clicks came from scans.',
    },
    {
        question: 'What happens when a link expires?',
        answer: 'Visitors see a not-found page instead of being redirected. You can change the expiry date or switch the link back on at any time.',
    },
    {
        question: 'Can I get my data or delete my account?',
        answer: 'Yes. In the profile settings you can download your data as a ZIP of CSV files or delete your account together with all your links and domains.',
    },
];

const periodLabels: Record<BillingPeriod, string> = {
    one_time: 'month (single payment)',
    monthly: 'month',
    quarterly: 'quarter',
    yearly: 'year',
};
</script>

<template>
    <Head title="Short links you own and understand" />

    <PublicLayout :legal-documents="legalDocuments" section-links>
        <section
            class="mx-auto w-full max-w-6xl px-4 py-12 text-center md:py-28"
        >
            <h1
                class="mx-auto max-w-3xl text-4xl font-semibold tracking-tight text-balance md:text-6xl"
            >
                Short links you own and understand
            </h1>
            <p
                class="mx-auto mt-6 max-w-2xl text-lg text-balance text-muted-foreground"
            >
                Create short links with your own aliases and domains, share them
                as QR codes and see how they perform, without tracking your
                visitors.
            </p>
            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                <Button v-if="page.props.auth.user" as-child size="lg">
                    <Link :href="dashboard()">
                        Go to the dashboard <ArrowRight class="size-4" />
                    </Link>
                </Button>
                <template v-else>
                    <Button as-child size="lg">
                        <Link :href="register()">
                            Create free account <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                </template>
            </div>
            <p class="mt-4 text-sm text-muted-foreground">
                Free package &middot; No credit card required &middot; No
                visitor IPs stored
            </p>
        </section>

        <section id="features" class="border-t bg-muted/30 py-12 md:py-20">
            <div class="mx-auto w-full max-w-6xl px-4">
                <h2
                    class="text-center text-2xl font-semibold tracking-tight md:text-3xl"
                >
                    Everything you need to manage your links
                </h2>
                <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <Card v-for="feature in features" :key="feature.title">
                        <CardHeader>
                            <component
                                :is="feature.icon"
                                class="mb-2 size-6 text-muted-foreground"
                            />
                            <CardTitle>{{ feature.title }}</CardTitle>
                        </CardHeader>
                        <CardContent class="text-sm text-muted-foreground">
                            {{ feature.text }}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </section>

        <section id="how-it-works" class="py-12 md:py-20">
            <div class="mx-auto w-full max-w-6xl px-4">
                <h2
                    class="text-center text-2xl font-semibold tracking-tight md:text-3xl"
                >
                    How it works
                </h2>
                <ol class="mt-10 grid gap-8 md:mt-12 md:grid-cols-3">
                    <li
                        v-for="(step, index) in steps"
                        :key="step.title"
                        class="flex flex-col items-center text-center"
                    >
                        <span
                            class="mb-4 flex size-10 items-center justify-center rounded-full bg-primary text-primary-foreground"
                            >{{ index + 1 }}</span
                        >
                        <h3 class="text-lg font-medium">{{ step.title }}</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ step.text }}
                        </p>
                    </li>
                </ol>
            </div>
        </section>

        <section
            v-if="packages.length"
            id="pricing"
            class="border-t bg-muted/30 py-12 md:py-20"
        >
            <div class="mx-auto w-full max-w-6xl px-4">
                <h2
                    class="text-center text-2xl font-semibold tracking-tight md:text-3xl"
                >
                    Start free, grow when you need to
                </h2>
                <div
                    class="mt-12 grid grid-cols-[repeat(auto-fit,minmax(16rem,1fr))] gap-6"
                >
                    <Card v-for="pkg in packages" :key="pkg.id">
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <CardTitle>{{ pkg.name }}</CardTitle>
                                <Badge v-if="pkg.is_default" variant="secondary"
                                    >Free</Badge
                                >
                            </div>
                            <p class="pt-2 text-2xl font-semibold">
                                <template v-if="pkg.price === null"
                                    >0 PLN</template
                                >
                                <template v-else>
                                    {{ formatPrice(pkg.price) }}
                                    <span
                                        class="text-sm font-normal text-muted-foreground"
                                        >/ {{ periodLabels[pkg.period!] }}</span
                                    >
                                </template>
                            </p>
                        </CardHeader>
                        <CardContent class="flex flex-1 flex-col gap-4">
                            <ul class="space-y-2 text-sm">
                                <li class="flex items-center gap-2">
                                    <Check
                                        class="size-4 text-muted-foreground"
                                    />
                                    {{ pkg.link_limit }} links
                                </li>
                                <li class="flex items-center gap-2">
                                    <Check
                                        class="size-4 text-muted-foreground"
                                    />
                                    {{
                                        pkg.domain_limit > 0
                                            ? `${pkg.domain_limit} custom domain${pkg.domain_limit > 1 ? 's' : ''}`
                                            : 'Shared domain'
                                    }}
                                </li>
                                <li
                                    v-if="pkg.allows_custom_alias"
                                    class="flex items-center gap-2"
                                >
                                    <Check
                                        class="size-4 text-muted-foreground"
                                    />
                                    Custom aliases
                                </li>
                                <li class="flex items-center gap-2">
                                    <Check
                                        class="size-4 text-muted-foreground"
                                    />
                                    QR codes and click analytics
                                </li>
                            </ul>
                            <Button
                                as-child
                                class="mt-auto"
                                :variant="
                                    pkg.is_default ? 'default' : 'outline'
                                "
                            >
                                <Link
                                    :href="
                                        page.props.auth.user
                                            ? dashboard()
                                            : register()
                                    "
                                >
                                    {{
                                        page.props.auth.user
                                            ? 'Open the dashboard'
                                            : 'Get started'
                                    }}
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </section>

        <section class="py-10 md:py-16">
            <div
                class="mx-auto grid w-full max-w-6xl gap-6 px-4 text-center sm:grid-cols-3"
            >
                <div
                    v-for="point in privacyPoints"
                    :key="point.text"
                    class="flex flex-col items-center gap-3"
                >
                    <component
                        :is="point.icon"
                        class="size-6 text-muted-foreground"
                    />
                    <p class="text-sm font-medium">{{ point.text }}</p>
                </div>
            </div>
        </section>

        <section id="faq" class="border-t py-12 md:py-20">
            <div class="mx-auto w-full max-w-3xl px-4">
                <h2
                    class="text-center text-2xl font-semibold tracking-tight md:text-3xl"
                >
                    Frequently asked questions
                </h2>
                <div class="mt-10 divide-y rounded-lg border">
                    <details
                        v-for="faq in faqs"
                        :key="faq.question"
                        class="group p-4"
                    >
                        <summary
                            class="flex cursor-pointer list-none items-center justify-between font-medium"
                        >
                            {{ faq.question }}
                            <span
                                class="ml-4 text-muted-foreground transition-transform group-open:rotate-45"
                                aria-hidden="true"
                                >+</span
                            >
                        </summary>
                        <p class="mt-3 text-sm text-muted-foreground">
                            {{ faq.answer }}
                        </p>
                    </details>
                </div>
            </div>
        </section>

        <section class="border-t bg-muted/30 py-12 text-center md:py-20">
            <div class="mx-auto w-full max-w-2xl px-4">
                <h2 class="text-2xl font-semibold tracking-tight md:text-3xl">
                    Ready to shorten your first link?
                </h2>
                <p class="mt-3 text-muted-foreground">
                    It takes less than a minute and the free package costs
                    nothing.
                </p>
                <Button as-child size="lg" class="mt-8">
                    <Link
                        :href="page.props.auth.user ? dashboard() : register()"
                    >
                        {{
                            page.props.auth.user
                                ? 'Go to the dashboard'
                                : 'Create free account'
                        }}
                        <ArrowRight class="size-4" />
                    </Link>
                </Button>
            </div>
        </section>
    </PublicLayout>
</template>
