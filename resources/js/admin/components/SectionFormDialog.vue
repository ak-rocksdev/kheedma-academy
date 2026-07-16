<script setup>
import { ref, computed, watch } from 'vue';
import { contentSections as sectionsApi } from '@/api';
import { Dialog } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import RichTextEditor from '@/components/RichTextEditor.vue';

const props = defineProps({
    /** Section (API shape) to edit; null = create mode. */
    section: { type: Object, default: null },
    /** Owner page for create mode: 'community' or 'program'. */
    page: { type: String, required: true },
    /** Program id, required when page === 'program'. */
    programId: { type: Number, default: null },
});

const open = defineModel('open', { type: Boolean, default: false });

const emit = defineEmits(['saved']);

const isEditing = computed(() => props.section !== null);

const form = ref({ heading: '', body: '' });
const formErrors = ref({});
const saving = ref(false);

// Every open re-seeds the form from the prop so a reopened dialog never shows
// stale values from the previous session.
watch(open, (isOpen) => {
    if (!isOpen) return;
    form.value = {
        heading: props.section?.heading ?? '',
        body: props.section?.body ?? '',
    };
    formErrors.value = {};
});

async function save() {
    saving.value = true;
    formErrors.value = {};
    try {
        if (isEditing.value) {
            await sectionsApi.update(props.section.id, form.value);
        } else {
            await sectionsApi.create({
                page: props.page,
                ...(props.page === 'program' ? { program_id: props.programId } : {}),
                ...form.value,
            });
        }
        emit('saved');
        open.value = false;
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        formErrors.value = e.errors ?? {};
        if (!Object.keys(formErrors.value).length) formErrors.value = { body: [e.message ?? 'Gagal menyimpan.'] };
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open" :title="isEditing ? 'Ubah Section' : 'Tambah Section'">
        <form class="space-y-4" @submit.prevent="save">
            <div>
                <label class="mb-1.5 block text-sm font-medium">Judul <span class="text-muted-foreground">(opsional)</span></label>
                <Input v-model="form.heading" placeholder="Judul kartu, mis. Jadwal belajar" />
                <p v-if="formErrors.heading" class="mt-1 text-xs text-destructive">{{ formErrors.heading[0] }}</p>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium">Isi</label>
                <RichTextEditor v-model="form.body" />
                <p v-if="formErrors.body" class="mt-1 text-xs text-destructive">{{ formErrors.body[0] }}</p>
            </div>
            <div class="flex justify-end gap-2">
                <Button type="button" variant="outline" @click="open = false">Batal</Button>
                <Button type="submit" :disabled="saving">{{ saving ? 'Menyimpan…' : 'Simpan' }}</Button>
            </div>
        </form>
    </Dialog>
</template>
