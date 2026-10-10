<script setup lang="ts">
import { Form, Head, Link as InertiaLink } from "@inertiajs/vue3";
import AdministratorPicker from "@/components/AdministratorPicker.vue";
import Heading from "@/components/Heading.vue";
import InputError from "@/components/InputError.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { AdministratorOption } from "@/types";
import { dashboard } from "@/routes/admin";
import { index, store } from "@/routes/admin/users";

defineProps<{
    administrators: AdministratorOption[];
    roles: { id: string; name: string }[];
    defaultRoleId: string | null;
}>();

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
    <Head title="Add user" />

    <div class="max-w-xl space-y-6 p-4">
        <Heading
            title="Add user"
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

            <div v-if="administrators.length > 0" class="grid gap-2">
                <Label>Assigned administrators</Label>
                <AdministratorPicker :administrators="administrators" />
                <InputError :message="errors.administrators" />
            </div>

            <div v-if="roles.length > 0" class="grid gap-2">
                <Label for="role_id">Role</Label>
                <select
                    id="role_id"
                    name="role_id"
                    :value="defaultRoleId ?? ''"
                    class="rounded-md border bg-background px-3 py-2 text-sm"
                >
                    <option
                        v-for="role in roles"
                        :key="role.id"
                        :value="role.id"
                    >
                        {{ role.name }}
                    </option>
                </select>
                <InputError :message="errors.role_id" />
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
