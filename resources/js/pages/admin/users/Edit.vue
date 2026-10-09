<script setup lang="ts">
import { Form, Head, useForm, Link as InertiaLink } from "@inertiajs/vue3";
import AdministratorPicker from "@/components/AdministratorPicker.vue";
import Heading from "@/components/Heading.vue";
import InputError from "@/components/InputError.vue";
import PasswordInput from "@/components/PasswordInput.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { update as updateAdministrators } from "@/actions/App/Http/Controllers/Admin/UserAdministratorController";
import { dashboard } from "@/routes/admin";
import type { AdministratorOption } from "@/types";
import { edit, index, show, update } from "@/routes/admin/users";

const props = defineProps<{
    user: { id: string; name: string; email: string };
    administrators: AdministratorOption[];
    assignedAdministratorIds: string[];
}>();

const administratorsForm = useForm({
    administrators: [...props.assignedAdministratorIds],
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: "Admin", href: dashboard() },
            { title: "Users", href: index() },
        ],
    },
});
</script>

<template>
    <Head title="Edit user" />

    <div class="max-w-xl space-y-6 p-4">
        <Heading
            title="Edit user"
            description="Update name, email address or password"
        />

        <Form
            v-bind="update.form(props.user.id)"
            reset-on-success
            :reset-on-error="['password', 'password_confirmation']"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="props.user.name"
                    required
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    :default-value="props.user.email"
                    required
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">New password (optional)</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    autocomplete="new-password"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                />
            </div>

            <div class="flex items-center gap-2">
                <Button :disabled="processing">Save</Button>
                <Button variant="outline" as-child>
                    <InertiaLink :href="show(props.user.id)"
                        >Cancel</InertiaLink
                    >
                </Button>
            </div>
        </Form>

        <form
            v-if="administrators.length > 0"
            class="space-y-4"
            @submit.prevent="
                administratorsForm.submit(updateAdministrators(props.user.id), {
                    preserveScroll: true,
                })
            "
        >
            <Heading title="Assigned administrators" />
            <AdministratorPicker
                v-model="administratorsForm.administrators"
                :administrators="administrators"
            />
            <InputError :message="administratorsForm.errors.administrators" />
            <Button :disabled="administratorsForm.processing">
                Save administrators
            </Button>
        </form>
    </div>
</template>
