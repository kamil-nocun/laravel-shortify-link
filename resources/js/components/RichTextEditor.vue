<script setup lang="ts">
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { onBeforeUnmount } from 'vue';
import { Button } from '@/components/ui/button';

const model = defineModel<string>({ required: true });

const editor = useEditor({
    content: model.value,
    extensions: [StarterKit.configure({ link: { openOnClick: false } })],
    editorProps: {
        attributes: {
            class: 'cms-content min-h-64 rounded-md border bg-background p-3 focus:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
        },
    },
    onUpdate: ({ editor }) => {
        model.value = editor.isEmpty ? '' : editor.getHTML();
    },
});

onBeforeUnmount(() => editor.value?.destroy());

function toggleLink(): void {
    if (!editor.value) {
        return;
    }

    if (editor.value.isActive('link')) {
        editor.value.chain().focus().unsetLink().run();

        return;
    }

    const url = window
        .prompt('Link URL (https://, http://, mailto: or /path)')
        ?.trim();

    if (!url) {
        return;
    }

    if (!/^(https?:|mailto:|\/(?!\/))/i.test(url)) {
        window.alert(
            'Use https://, http://, mailto: or a path starting with /',
        );

        return;
    }

    editor.value.chain().focus().setLink({ href: url }).run();
}
</script>

<template>
    <div v-if="editor" class="space-y-2">
        <div class="flex flex-wrap gap-1">
            <Button
                type="button"
                size="sm"
                :variant="editor.isActive('bold') ? 'default' : 'outline'"
                @click="editor.chain().focus().toggleBold().run()"
                >Bold</Button
            >
            <Button
                type="button"
                size="sm"
                :variant="editor.isActive('italic') ? 'default' : 'outline'"
                @click="editor.chain().focus().toggleItalic().run()"
                >Italic</Button
            >
            <Button
                type="button"
                size="sm"
                :variant="
                    editor.isActive('heading', { level: 2 })
                        ? 'default'
                        : 'outline'
                "
                @click="
                    editor.chain().focus().toggleHeading({ level: 2 }).run()
                "
                >Heading</Button
            >
            <Button
                type="button"
                size="sm"
                :variant="editor.isActive('bulletList') ? 'default' : 'outline'"
                @click="editor.chain().focus().toggleBulletList().run()"
                >Bullets</Button
            >
            <Button
                type="button"
                size="sm"
                :variant="
                    editor.isActive('orderedList') ? 'default' : 'outline'
                "
                @click="editor.chain().focus().toggleOrderedList().run()"
                >Numbers</Button
            >
            <Button
                type="button"
                size="sm"
                :variant="editor.isActive('blockquote') ? 'default' : 'outline'"
                @click="editor.chain().focus().toggleBlockquote().run()"
                >Quote</Button
            >
            <Button
                type="button"
                size="sm"
                :variant="editor.isActive('link') ? 'default' : 'outline'"
                @click="toggleLink"
                >Link</Button
            >
        </div>
        <EditorContent :editor="editor" />
    </div>
</template>
