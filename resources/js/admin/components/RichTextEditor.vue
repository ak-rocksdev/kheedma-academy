<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import { Bold, Italic, List, ListOrdered, Link2, ImagePlus } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import MediaPickerDialog from '@/components/MediaPickerDialog.vue';

/**
 * Schema-constrained rich text editor. Only the nodes/marks the public
 * .kh-prose renderer supports exist in the schema, so pasted content is
 * normalized and the editor canvas IS the public preview.
 */
const model = defineModel({ type: String, default: '' });

const pickerOpen = ref(false);
const linkFormOpen = ref(false);
const linkUrl = ref('');

const editor = useEditor({
    content: model.value,
    extensions: [
        StarterKit.configure({
            heading: false,
            blockquote: false,
            code: false,
            codeBlock: false,
            horizontalRule: false,
            strike: false,
            // Registered separately below so `Link.configure` controls its
            // options; StarterKit v3 bundles its own Link and would otherwise
            // register a duplicate.
            link: false,
        }),
        Link.configure({ openOnClick: false }),
        Image,
    ],
    editorProps: {
        attributes: { class: 'kh-prose min-h-40 focus:outline-none' },
    },
    onUpdate: ({ editor: instance }) => {
        model.value = instance.getHTML();
    },
});

// External model swaps (dialog re-seeded for another section) reset the doc.
watch(model, (value) => {
    if (editor.value && editor.value.getHTML() !== value) {
        editor.value.commands.setContent(value || '', { emitUpdate: false });
    }
});

onBeforeUnmount(() => editor.value?.destroy());

function toggleLinkForm() {
    if (editor.value.isActive('link')) {
        editor.value.chain().focus().unsetLink().run();
        return;
    }
    linkUrl.value = '';
    linkFormOpen.value = !linkFormOpen.value;
}

function applyLink() {
    if (linkUrl.value) {
        const chain = editor.value.chain().focus().extendMarkRange('link');
        if (editor.value.state.selection.empty) {
            // No selection: insert the URL as its own link text (covers
            // pasting a copied media-file link, e.g. a PDF from Media).
            chain.insertContent(`<a href="${linkUrl.value}">${linkUrl.value}</a>`).run();
        } else {
            chain.setLink({ href: linkUrl.value }).run();
        }
    }
    linkFormOpen.value = false;
}

function insertImage(item) {
    editor.value.chain().focus().setImage({ src: item.url, alt: item.alt_text ?? '' }).run();
}

const buttons = [
    { icon: Bold, title: 'Tebal', isActive: () => editor.value?.isActive('bold'), run: () => editor.value.chain().focus().toggleBold().run() },
    { icon: Italic, title: 'Miring', isActive: () => editor.value?.isActive('italic'), run: () => editor.value.chain().focus().toggleItalic().run() },
    { icon: List, title: 'Daftar', isActive: () => editor.value?.isActive('bulletList'), run: () => editor.value.chain().focus().toggleBulletList().run() },
    { icon: ListOrdered, title: 'Daftar bernomor', isActive: () => editor.value?.isActive('orderedList'), run: () => editor.value.chain().focus().toggleOrderedList().run() },
];
</script>

<template>
    <div class="rounded-lg border border-input bg-white">
        <div class="flex flex-wrap items-center gap-1 border-b border-input p-1.5">
            <Button
                v-for="button in buttons"
                :key="button.title"
                type="button"
                variant="ghost"
                size="icon"
                class="h-8 w-8"
                :class="button.isActive() && 'bg-accent text-accent-foreground'"
                :title="button.title"
                @click="button.run"
            >
                <component :is="button.icon" class="h-4 w-4" />
            </Button>
            <Button
                type="button" variant="ghost" size="icon" class="h-8 w-8"
                :class="editor?.isActive('link') && 'bg-accent text-accent-foreground'"
                title="Tautan" @click="toggleLinkForm"
            >
                <Link2 class="h-4 w-4" />
            </Button>
            <Button type="button" variant="ghost" size="icon" class="h-8 w-8" title="Sisipkan gambar" @click="pickerOpen = true">
                <ImagePlus class="h-4 w-4" />
            </Button>
        </div>

        <div v-if="linkFormOpen" class="flex items-center gap-2 border-b border-input p-2">
            <Input v-model="linkUrl" placeholder="https://…" class="h-8 text-sm" @keydown.enter.prevent="applyLink" />
            <Button type="button" size="sm" @click="applyLink">Pasang</Button>
        </div>

        <!-- White canvas: same .kh-prose as the public page = true WYSIWYG. -->
        <div class="rounded-b-lg bg-white px-4 py-3">
            <EditorContent :editor="editor" />
        </div>

        <MediaPickerDialog v-model:open="pickerOpen" @picked="insertImage" />
    </div>
</template>
