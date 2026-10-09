<script setup lang="ts">
import { Form, Head, Link as InertiaLink } from "@inertiajs/vue3";
import Heading from "@/components/Heading.vue";
import InputError from "@/components/InputError.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { dashboard } from "@/routes/admin";
import { index, store } from "@/routes/admin/administrators";

defineOptions({
    layout: {
        breadcrumbs: [
            { title: "Admin", href: dashboard() },
            { title: "Administrators", href: index() },
        ],
    },
});
</script>

<template>
    <Head title="Add administrator" />

    <div class="max-w-xl space-y-6 p-4">
        <Heading
            title="Add administrator"
            description="The user receives an email with a link to set their own password"
        />

        <Form
            v-bind="store.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" name="name" required />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input id="email" type="email" name="email" required />
                <InputError :message="errors.email" />
            </div>

            <div class="flex items-center gap-2">
                <Button :disabled="processing">Create and send link</Button>
                <Button variant="outline" as-child>
                    <InertiaLink :href="index()">Cancel</InertiaLink>
                </Button>
            </div>
        </Form>
    </div>
</template>
