<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Link } from '@/types';

const props = defineProps<{
    link?: Link;
    submitLabel: string;
    action: (form: ReturnType<typeof useLinkForm>) => void;
}>();

function toLocalInput(iso: string | null | undefined): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);

    return new Date(date.getTime() - date.getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 16);
}

function useLinkForm() {
    return useForm({
        original_url: props.link?.original_url ?? '',
        title: props.link?.title ?? '',
        is_active: props.link?.is_active ?? true,
        expires_at: toLocalInput(props.link?.expires_at),
    }).transform((data) => ({
        ...data,
        expires_at: data.expires_at
            ? new Date(data.expires_at).toISOString()
            : null,
    }));
}

const form = useLinkForm();
</script>

<template>
    <form class="space-y-6" @submit.prevent="action(form)">
        <div v-if="link" class="grid gap-2">
            <Label for="code">Short code</Label>
            <Input id="code" :model-value="link.code" readonly />
        </div>

        <div class="grid gap-2">
            <Label for="original_url">Target URL</Label>
            <Input
                id="original_url"
                v-model="form.original_url"
                type="url"
                required
                placeholder="https://example.com/long/path"
            />
            <InputError :message="form.errors.original_url" />
        </div>

        <div class="grid gap-2">
            <Label for="title">Title</Label>
            <Input id="title" v-model="form.title" placeholder="Optional" />
            <InputError :message="form.errors.title" />
        </div>

        <div class="grid gap-2">
            <Label for="expires_at">Expires at</Label>
            <Input
                id="expires_at"
                v-model="form.expires_at"
                type="datetime-local"
            />
            <InputError :message="form.errors.expires_at" />
        </div>

        <div class="flex items-center gap-2">
            <Checkbox
                id="is_active"
                :model-value="form.is_active"
                @update:model-value="form.is_active = $event === true"
            />
            <Label for="is_active">Active</Label>
        </div>

        <Button type="submit" :disabled="form.processing">{{
            submitLabel
        }}</Button>
    </form>
</template>
