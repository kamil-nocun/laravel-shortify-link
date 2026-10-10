<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import InputError from '@/components/InputError.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PageContent } from '@/types';

const props = defineProps<{
    page?: PageContent;
    submitLabel: string;
    action: (form: ReturnType<typeof usePageForm>) => void;
}>();

function usePageForm() {
    return useForm({
        slug: props.page?.slug ?? '',
        title: props.page?.title ?? '',
        body: props.page?.body ?? '',
        is_legal_document: props.page?.is_legal_document ?? false,
    });
}

const form = usePageForm();

const emit = defineEmits<{ dirty: [value: boolean] }>();

watch(
    () => form.isDirty,
    (isDirty) => emit('dirty', isDirty),
);
</script>

<template>
    <form class="space-y-6" @submit.prevent="action(form)">
        <div class="grid gap-2">
            <Label for="slug">Slug</Label>
            <Input
                id="slug"
                v-model="form.slug"
                required
                placeholder="privacy-policy"
            />
            <p class="text-sm text-muted-foreground">
                Public address: /content/{{ form.slug || 'slug' }}
            </p>
            <p
                v-if="page?.status === 'published' && form.slug !== page.slug"
                class="text-sm text-amber-600"
            >
                The published address /content/{{ page.slug }} will stop working
                after you save.
            </p>
            <InputError :message="form.errors.slug" />
        </div>

        <div class="grid gap-2">
            <Label for="title">Title</Label>
            <Input id="title" v-model="form.title" required />
            <InputError :message="form.errors.title" />
        </div>

        <div class="grid gap-2">
            <div class="flex items-start gap-2">
                <Checkbox
                    id="is_legal_document"
                    :model-value="form.is_legal_document"
                    @update:model-value="
                        form.is_legal_document = $event === true
                    "
                />
                <Label for="is_legal_document" class="leading-normal"
                    >Legal document (users must accept it)</Label
                >
            </div>
            <InputError :message="form.errors.is_legal_document" />
        </div>

        <div class="grid gap-2">
            <Label>Content</Label>
            <RichTextEditor v-model="form.body" />
            <InputError :message="form.errors.body" />
        </div>

        <Button type="submit" :disabled="form.processing">{{
            submitLabel
        }}</Button>
    </form>
</template>
