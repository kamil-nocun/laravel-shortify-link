<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { show } from '@/routes/content';
import { store } from '@/routes/consent';

defineProps<{
    documents: { id: string; title: string; slug: string }[];
}>();

defineOptions({
    layout: {
        title: 'Accept the current documents',
        description:
            'We updated the documents below. Please read and accept them to continue.',
    },
});
</script>

<template>
    <Head title="Accept the current documents" />

    <Form
        v-bind="store.form()"
        v-slot="{ processing }"
        class="flex flex-col gap-6"
    >
        <ul class="list-disc space-y-2 pl-5 text-sm">
            <li v-for="document in documents" :key="document.id">
                <a
                    :href="show(document.slug).url"
                    target="_blank"
                    rel="noopener"
                    class="underline"
                    >{{ document.title }}</a
                >
            </li>
        </ul>

        <Button type="submit" class="w-full" :disabled="processing">
            <Spinner v-if="processing" />
            Accept and continue
        </Button>
    </Form>
</template>
