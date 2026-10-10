<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import DateTimePicker from '@/components/DateTimePicker.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Link } from '@/types';

const props = defineProps<{
    link?: Link;
    domains?: { id: string; host: string }[];
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
        domain_id: '',
        original_url: props.link?.original_url ?? '',
        title: props.link?.title ?? '',
        is_active: props.link?.is_active ?? true,
        expires_at: toLocalInput(props.link?.expires_at),
    }).transform((data) => ({
        ...data,
        domain_id: data.domain_id || null,
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
            <Label for="short_url">Short URL</Label>
            <Input id="short_url" :model-value="link.short_url" readonly />
        </div>

        <div v-if="!link && domains?.length" class="grid gap-2">
            <Label for="domain_id">Domain</Label>
            <select
                id="domain_id"
                v-model="form.domain_id"
                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
            >
                <option value=""></option>
                <option v-for="d in domains" :key="d.id" :value="d.id">
                    {{ d.host }}
                </option>
            </select>
            <InputError :message="form.errors.domain_id" />
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
            <DateTimePicker id="expires_at" v-model="form.expires_at" />
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
