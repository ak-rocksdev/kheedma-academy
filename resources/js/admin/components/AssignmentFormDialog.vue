<script setup>
import { ref, computed, watch } from 'vue';
import { assignments as assignmentsApi } from '@/api';
import { Input } from '@/components/ui/input';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';

const props = defineProps({
    /** Session row (API shape) whose assignment is being written/edited. */
    session: { type: Object, default: null },
});

const open = defineModel('open', { type: Boolean, default: false });
const emit = defineEmits(['saved']);

const isEditing = computed(() => props.session?.assignment != null);

const form = ref({});
const formErrors = ref({});
const saving = ref(false);

/**
 * Legacy bodies (pre rich-text) are plain text; Tiptap would collapse their
 * line breaks into one paragraph. Convert them to paragraphs/breaks once on
 * open — after the first save the stored body is HTML and passes through.
 */
function toEditorHtml(body) {
    if (!body) return '';
    if (/<(p|ul|ol|li|h[1-6]|strong|em|a|br|blockquote|img)\b/i.test(body)) return body;
    const escape = (t) => t.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');

    // The editor schema has hardBreak disabled, so every line becomes its
    // own paragraph (numbered lines stay on their own line).
    return body
        .split(/\n+/)
        .filter((line) => line.trim() !== '')
        .map((line) => `<p>${escape(line)}</p>`)
        .join('');
}

// Every open re-seeds the form so a reopened dialog never shows stale values.
watch(open, (isOpen) => {
    if (!isOpen) return;
    form.value = {
        title: props.session?.assignment?.title ?? '',
        body: toEditorHtml(props.session?.assignment?.body ?? ''),
    };
    formErrors.value = {};
});

async function save() {
    saving.value = true;
    formErrors.value = {};
    try {
        const res = await assignmentsApi.upsert(props.session.id, { title: form.value.title, body: form.value.body });
        open.value = false;
        emit('saved', res.assignment);
    } catch (e) {
        if (e.sessionExpired) return;
        formErrors.value = e.errors ?? {};
        if (!Object.keys(formErrors.value).length) formErrors.value = { title: [e.message ?? 'Gagal menyimpan.'] };
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open" wide :title="isEditing ? 'Ubah Tugas' : 'Tulis Tugas'">
        <form class="space-y-3" @submit.prevent="save">
            <div>
                <label class="text-xs text-muted-foreground">Judul tugas</label>
                <Input v-model="form.title" placeholder="Contoh: Riset 3 produk winning" class="mt-1.5" />
                <p v-if="formErrors.title" class="mt-1 text-xs text-destructive">{{ formErrors.title[0] }}</p>
            </div>
            <div>
                <label class="text-xs text-muted-foreground">Soal / instruksi untuk peserta</label>
                <div class="mt-1.5">
                    <RichTextEditor v-model="form.body" />
                </div>
                <p class="mt-1.5 text-xs text-muted-foreground">Gunakan heading, daftar, tebal, link, atau gambar supaya instruksi panjang tetap mudah dibaca peserta.</p>
                <p v-if="formErrors.body" class="mt-1 text-xs text-destructive">{{ formErrors.body[0] }}</p>
            </div>
            <p v-if="isEditing && session?.assignment?.updated_by" class="text-xs text-muted-foreground">
                Terakhir diubah oleh {{ session.assignment.updated_by }}.
            </p>
            <div class="mt-4 flex justify-end gap-2 border-t border-border pt-4">
                <Button type="button" variant="outline" size="sm" @click="open = false">Batal</Button>
                <Button type="submit" size="sm" :disabled="saving">{{ saving ? 'Menyimpan…' : 'Simpan' }}</Button>
            </div>
        </form>
    </Dialog>
</template>
