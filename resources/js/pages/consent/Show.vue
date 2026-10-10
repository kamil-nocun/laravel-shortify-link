<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import DeleteUser from '@/components/DeleteUser.vue';
import DownloadUserData from '@/components/DownloadUserData.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { store } from '@/routes/consent';
import { show } from '@/routes/content';

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

    <div class="flex flex-col gap-8">
        <Form
            v-bind="store.form()"
            v-slot="{ processing }"
            class="flex flex-col gap-6"
        >
            <input
                v-for="document in documents"
                :key="document.id"
                type="hidden"
                name="versions[]"
                :value="document.id"
            />

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

            <TextLink
                :href="logout()"
                as="button"
                class="mx-auto block text-sm"
            >
                Log out
            </TextLink>
        </Form>

        <DownloadUserData />

        <DeleteUser />
    </div>
</template>
