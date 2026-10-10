<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { role as updateRole } from '@/routes/admin/users';
import { update as updatePermissions } from '@/routes/admin/users/permissions';
import type { AdminUser, PermissionGroup } from '@/types';

type OverrideState = 'inherited' | 'granted' | 'revoked';

/** Granted only through the role, never as a per-user override. */
const ROLE_ONLY_PERMISSION = 'admin.access';

const props = defineProps<{
    user: AdminUser;
    roles: { id: string; name: string }[];
    permissionGroups: PermissionGroup[];
    rolePermissions: string[];
    permissionOverrides: { granted: string[]; revoked: string[] };
}>();

const roleForm = useForm({ role_id: props.user.role_id ?? '' });

const changeRole = () =>
    roleForm.patch(updateRole(props.user.id).url, { preserveScroll: true });

/** A legacy admin.access override would make every save fail, so it is dropped from the form. */
const permissionsForm = useForm({
    granted: props.permissionOverrides.granted.filter(
        (key) => key !== ROLE_ONLY_PERMISSION,
    ),
    revoked: props.permissionOverrides.revoked.filter(
        (key) => key !== ROLE_ONLY_PERMISSION,
    ),
});

const labelOf = (key: string): string =>
    props.permissionGroups
        .flatMap((group) => group.permissions)
        .find((permission) => permission.key === key)?.label ?? key;

/** Prefix an error on `granted.N` / `revoked.N` with the label of the permission it refers to. */
const errorMessage = (
    field: string,
    message: string | undefined,
): string | undefined => {
    const [list, index] = field.split('.');

    if (
        !message ||
        (list !== 'granted' && list !== 'revoked') ||
        index === undefined
    ) {
        return message;
    }

    const key = permissionsForm[list][Number(index)];

    return key ? `${labelOf(key)}: ${message}` : message;
};

const stateOf = (key: string): OverrideState =>
    permissionsForm.granted.includes(key)
        ? 'granted'
        : permissionsForm.revoked.includes(key)
          ? 'revoked'
          : 'inherited';

const setState = (key: string, state: OverrideState) => {
    permissionsForm.granted = permissionsForm.granted.filter(
        (existing) => existing !== key,
    );
    permissionsForm.revoked = permissionsForm.revoked.filter(
        (existing) => existing !== key,
    );

    if (state !== 'inherited') {
        permissionsForm[state].push(key);
    }
};

const savePermissions = () =>
    permissionsForm.put(updatePermissions(props.user.id).url, {
        preserveScroll: true,
    });
</script>

<template>
    <div class="space-y-4 rounded-lg border p-4">
        <h2 class="font-medium">Role and permissions</h2>

        <form
            class="flex max-w-xl items-start gap-2"
            @submit.prevent="changeRole"
        >
            <div class="grid flex-1 gap-2">
                <Label for="role_id">Role</Label>
                <select
                    id="role_id"
                    v-model="roleForm.role_id"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                >
                    <option
                        v-for="role in roles"
                        :key="role.id"
                        :value="role.id"
                    >
                        {{ role.name }}
                    </option>
                </select>
                <InputError :message="roleForm.errors.role_id" />
            </div>
            <Button
                type="submit"
                size="sm"
                class="mt-7"
                :disabled="
                    roleForm.processing || roleForm.role_id === user.role_id
                "
                >Change role</Button
            >
        </form>

        <form class="space-y-3" @submit.prevent="savePermissions">
            <p class="text-sm text-muted-foreground">
                Inherited permissions follow the role; granted and revoked
                override it for this user only.
            </p>

            <fieldset
                v-for="group in permissionGroups"
                :key="group.group"
                class="rounded-lg border p-3"
            >
                <legend class="px-1 text-sm font-medium">
                    {{ group.group }}
                </legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    <div
                        v-for="permission in group.permissions"
                        :key="permission.key"
                        class="flex items-center justify-between gap-2 text-sm"
                    >
                        <span class="flex flex-wrap items-center gap-1">
                            {{ permission.label }}
                            <Badge
                                v-if="rolePermissions.includes(permission.key)"
                                variant="secondary"
                                >from role</Badge
                            >
                            <span
                                v-if="permission.key === ROLE_ONLY_PERMISSION"
                                class="text-xs text-muted-foreground"
                                >(set by the role only)</span
                            >
                        </span>
                        <select
                            :value="stateOf(permission.key)"
                            :aria-label="`${permission.label}: override`"
                            :disabled="permission.key === ROLE_ONLY_PERMISSION"
                            class="h-8 rounded-md border bg-background px-2 text-sm"
                            @change="
                                setState(
                                    permission.key,
                                    ($event.target as HTMLSelectElement)
                                        .value as OverrideState,
                                )
                            "
                        >
                            <option value="inherited">Inherited</option>
                            <option value="granted">Granted</option>
                            <option value="revoked">Revoked</option>
                        </select>
                    </div>
                </div>
            </fieldset>

            <InputError
                v-for="(message, field) in permissionsForm.errors"
                :key="field"
                :message="errorMessage(String(field), message)"
            />

            <Button
                type="submit"
                size="sm"
                :disabled="permissionsForm.processing"
                >Save permissions</Button
            >
        </form>
    </div>
</template>
