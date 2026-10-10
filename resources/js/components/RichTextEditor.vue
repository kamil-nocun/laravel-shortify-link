<script setup lang="ts">
import {
    AlignCenter,
    AlignJustify,
    AlignLeft,
    AlignRight,
    Bold,
    Code,
    Heading1,
    Heading2,
    Heading3,
    Image as ImageIcon,
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
    Table as TableIcon,
    Underline,
    Undo2,
} from '@lucide/vue';
import Image from '@tiptap/extension-image';
import { TableKit } from '@tiptap/extension-table';
import TextAlign from '@tiptap/extension-text-align';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import type { Editor } from '@tiptap/vue-3';
import type { Component } from 'vue';
import { onBeforeUnmount } from 'vue';
import { Button } from '@/components/ui/button';

type ToolbarItem = {
    label: string;
    icon?: Component;
    text?: string;
    run: (editor: Editor) => void;
    isActive?: (editor: Editor) => boolean;
    isDisabled?: (editor: Editor) => boolean;
};

const model = defineModel<string>({ required: true });

const editor = useEditor({
    content: model.value,
    extensions: [
        StarterKit.configure({ link: { openOnClick: false } }),
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        TableKit,
        Image,
    ],
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

function insertImage(instance: Editor): void {
    const src = window.prompt('Image URL (https://)')?.trim();

    if (!src) {
        return;
    }

    if (!/^https:\/\//i.test(src)) {
        window.alert('Only https:// image URLs are allowed');

        return;
    }

    const alt = window.prompt('Image description (alt text)')?.trim() ?? '';

    instance.chain().focus().setImage({ src, alt }).run();
}

function align(
    alignment: 'left' | 'center' | 'right' | 'justify',
    icon: Component,
): ToolbarItem {
    return {
        label: `Align ${alignment}`,
        icon,
        run: (e) => e.chain().focus().setTextAlign(alignment).run(),
        isActive: (e) => e.isActive({ textAlign: alignment }),
    };
}

function heading(level: 1 | 2 | 3, icon: Component): ToolbarItem {
    return {
        label: `Heading ${level}`,
        icon,
        run: (e) => e.chain().focus().toggleHeading({ level }).run(),
        isActive: (e) => e.isActive('heading', { level }),
    };
}

type ToolbarGroup = {
    items: ToolbarItem[];
    visible?: (editor: Editor) => boolean;
};

const groups: ToolbarGroup[] = [
    {
        items: [
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
    },
    {
        items: [
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
    },
    {
        items: [
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
    },
    {
        items: [
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
    },
    {
        items: [
            {
                label: 'Link',
                icon: Link,
                run: toggleLink,
                isActive: (e) => e.isActive('link'),
            },
            {
                label: 'Clear formatting',
                icon: RemoveFormatting,
                run: (e) =>
                    e.chain().focus().unsetAllMarks().clearNodes().run(),
            },
        ],
    },
    {
        items: [
            align('left', AlignLeft),
            align('center', AlignCenter),
            align('right', AlignRight),
            align('justify', AlignJustify),
        ],
    },
    {
        items: [
            {
                label: 'Insert table',
                icon: TableIcon,
                run: (e) =>
                    e
                        .chain()
                        .focus()
                        .insertTable({ rows: 3, cols: 3, withHeaderRow: true })
                        .run(),
            },
            { label: 'Insert image', icon: ImageIcon, run: insertImage },
        ],
    },
    {
        visible: (e) => e.isActive('table'),
        items: [
            {
                label: 'Add row below',
                text: '+ Row',
                run: (e) => e.chain().focus().addRowAfter().run(),
            },
            {
                label: 'Add column after',
                text: '+ Col',
                run: (e) => e.chain().focus().addColumnAfter().run(),
            },
            {
                label: 'Delete row',
                text: '− Row',
                run: (e) => e.chain().focus().deleteRow().run(),
            },
            {
                label: 'Delete column',
                text: '− Col',
                run: (e) => e.chain().focus().deleteColumn().run(),
            },
            {
                label: 'Toggle header row',
                text: 'Header',
                run: (e) => e.chain().focus().toggleHeaderRow().run(),
            },
            {
                label: 'Delete table',
                text: 'Delete table',
                run: (e) => e.chain().focus().deleteTable().run(),
            },
        ],
    },
];
</script>

<template>
    <div v-if="editor" class="space-y-2">
        <div
            class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-md border bg-muted/30 p-1"
            role="toolbar"
            aria-label="Formatting"
        >
            <template v-for="(group, index) in groups" :key="index">
                <div
                    v-if="group.visible?.(editor) ?? true"
                    class="flex gap-0.5"
                >
                    <Button
                        v-for="item in group.items"
                        :key="item.label"
                        type="button"
                        :size="item.text ? 'sm' : 'icon'"
                        :class="item.text ? 'h-8 px-2 text-xs' : 'size-8'"
                        :variant="item.isActive?.(editor) ? 'default' : 'ghost'"
                        :disabled="item.isDisabled?.(editor)"
                        :title="item.label"
                        :aria-label="item.label"
                        :aria-pressed="item.isActive?.(editor)"
                        @click="item.run(editor)"
                    >
                        <component
                            :is="item.icon"
                            v-if="item.icon"
                            class="size-4"
                        />
                        <template v-else>{{ item.text }}</template>
                    </Button>
                </div>
            </template>
        </div>
        <EditorContent :editor="editor" />
    </div>
</template>
