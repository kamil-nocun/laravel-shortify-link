<script setup lang="ts">
import {
    Bold,
    Code,
    Heading1,
    Heading2,
    Heading3,
    Italic,
    List,
    ListOrdered,
    Link,
    Minus,
    Pilcrow,
    Quote,
    Redo2,
    RemoveFormatting,
    SquareCode,
    Strikethrough,
    Underline,
    Undo2,
} from '@lucide/vue';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import type { Editor } from '@tiptap/vue-3';
import type { Component } from 'vue';
import { onBeforeUnmount } from 'vue';
import { Button } from '@/components/ui/button';

type ToolbarItem = {
    label: string;
    icon: Component;
    run: (editor: Editor) => void;
    isActive?: (editor: Editor) => boolean;
    isDisabled?: (editor: Editor) => boolean;
};

const model = defineModel<string>({ required: true });

const editor = useEditor({
    content: model.value,
    extensions: [StarterKit.configure({ link: { openOnClick: false } })],
    editorProps: {
        attributes: {
            class: 'cms-content min-h-72 rounded-md border bg-background p-3 focus:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
        },
    },
    onUpdate: ({ editor }) => {
        model.value = editor.isEmpty ? '' : editor.getHTML();
    },
});

onBeforeUnmount(() => editor.value?.destroy());

function toggleLink(instance: Editor): void {
    if (instance.isActive('link')) {
        instance.chain().focus().unsetLink().run();

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

    instance.chain().focus().setLink({ href: url }).run();
}

function heading(level: 1 | 2 | 3, icon: Component): ToolbarItem {
    return {
        label: `Heading ${level}`,
        icon,
        run: (e) => e.chain().focus().toggleHeading({ level }).run(),
        isActive: (e) => e.isActive('heading', { level }),
    };
}

const groups: ToolbarItem[][] = [
    [
        {
            label: 'Undo',
            icon: Undo2,
            run: (e) => e.chain().focus().undo().run(),
            isDisabled: (e) => !e.can().undo(),
        },
        {
            label: 'Redo',
            icon: Redo2,
            run: (e) => e.chain().focus().redo().run(),
            isDisabled: (e) => !e.can().redo(),
        },
    ],
    [
        {
            label: 'Paragraph',
            icon: Pilcrow,
            run: (e) => e.chain().focus().setParagraph().run(),
            isActive: (e) => e.isActive('paragraph'),
        },
        heading(1, Heading1),
        heading(2, Heading2),
        heading(3, Heading3),
    ],
    [
        {
            label: 'Bold',
            icon: Bold,
            run: (e) => e.chain().focus().toggleBold().run(),
            isActive: (e) => e.isActive('bold'),
        },
        {
            label: 'Italic',
            icon: Italic,
            run: (e) => e.chain().focus().toggleItalic().run(),
            isActive: (e) => e.isActive('italic'),
        },
        {
            label: 'Underline',
            icon: Underline,
            run: (e) => e.chain().focus().toggleUnderline().run(),
            isActive: (e) => e.isActive('underline'),
        },
        {
            label: 'Strikethrough',
            icon: Strikethrough,
            run: (e) => e.chain().focus().toggleStrike().run(),
            isActive: (e) => e.isActive('strike'),
        },
        {
            label: 'Inline code',
            icon: Code,
            run: (e) => e.chain().focus().toggleCode().run(),
            isActive: (e) => e.isActive('code'),
        },
    ],
    [
        {
            label: 'Bullet list',
            icon: List,
            run: (e) => e.chain().focus().toggleBulletList().run(),
            isActive: (e) => e.isActive('bulletList'),
        },
        {
            label: 'Numbered list',
            icon: ListOrdered,
            run: (e) => e.chain().focus().toggleOrderedList().run(),
            isActive: (e) => e.isActive('orderedList'),
        },
        {
            label: 'Quote',
            icon: Quote,
            run: (e) => e.chain().focus().toggleBlockquote().run(),
            isActive: (e) => e.isActive('blockquote'),
        },
        {
            label: 'Code block',
            icon: SquareCode,
            run: (e) => e.chain().focus().toggleCodeBlock().run(),
            isActive: (e) => e.isActive('codeBlock'),
        },
        {
            label: 'Horizontal rule',
            icon: Minus,
            run: (e) => e.chain().focus().setHorizontalRule().run(),
        },
    ],
    [
        {
            label: 'Link',
            icon: Link,
            run: toggleLink,
            isActive: (e) => e.isActive('link'),
        },
        {
            label: 'Clear formatting',
            icon: RemoveFormatting,
            run: (e) => e.chain().focus().unsetAllMarks().clearNodes().run(),
        },
    ],
];
</script>

<template>
    <div v-if="editor" class="space-y-2">
        <div
            class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-md border bg-muted/30 p-1"
            role="toolbar"
            aria-label="Formatting"
        >
            <div
                v-for="(group, index) in groups"
                :key="index"
                class="flex gap-0.5"
            >
                <Button
                    v-for="item in group"
                    :key="item.label"
                    type="button"
                    size="icon"
                    class="size-8"
                    :variant="item.isActive?.(editor) ? 'default' : 'ghost'"
                    :disabled="item.isDisabled?.(editor)"
                    :title="item.label"
                    :aria-label="item.label"
                    :aria-pressed="item.isActive?.(editor)"
                    @click="item.run(editor)"
                >
                    <component :is="item.icon" class="size-4" />
                </Button>
            </div>
        </div>
        <EditorContent :editor="editor" />
    </div>
</template>
