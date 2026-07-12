<script setup>
import { ref, computed, onUnmounted, watch } from 'vue';
import { ImagePlus } from 'lucide-vue-next';
import { programs as programsApi } from '@/api';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

const props = defineProps({
    /** Program row (API shape) to edit; null opens the dialog in create mode. */
    program: { type: Object, default: null },
});

const open = defineModel('open', { type: Boolean, default: false });

const emit = defineEmits(['saved', 'thumbnail-changed']);

const isEditing = computed(() => props.program !== null);

const form = ref({});
const formErrors = ref({});
const saving = ref(false);

// Thumbnail state is local: the prop stays untouched, parents re-fetch on the
// thumbnail-changed event instead.
const thumbnailUrl = ref(null);
const thumbError = ref('');

const STATUS_OPTIONS = [
    { value: 'draft', label: 'Draf' },
    { value: 'active', label: 'Aktif' },
    { value: 'inactive', label: 'Nonaktif' },
];

const MODE_OPTIONS = [
    { value: 'selective', label: 'Selektif' },
    { value: 'instant', label: 'Langsung masuk' },
];

const TYPE_OPTIONS = [
    { value: 'general', label: 'Program Umum' },
    { value: 'affiliate_community', label: 'Affiliate Community' },
];

/** The slug is derived, never typed: live from the name on create, frozen on edit. */
const previewSlug = computed(() => (isEditing.value ? props.program.slug : form.value.slug));

function slugify(text) {
    return text.toLowerCase().trim().replace(/[^a-z0-9\s-]/g, '').replace(/[\s-]+/g, '-').replace(/^-|-$/g, '');
}

// Every open re-seeds the form from the prop so a reopened dialog never shows
// stale values from the previous session.
watch(open, (isOpen) => {
    if (!isOpen) {
        previewOpen.value = false;
        return;
    }
    form.value = {
        name: props.program?.name ?? '',
        slug: props.program?.slug ?? '',
        tagline: props.program?.tagline ?? '',
        description: props.program?.description ?? '',
        status: props.program?.status ?? 'draft',
        selection_mode: props.program?.selection_mode ?? 'selective',
        type: props.program?.type ?? 'general',
        level: props.program?.level ?? '',
        locked_message: props.program?.locked_message ?? '',
    };
    thumbnailUrl.value = props.program?.thumbnail_url ?? null;
    formErrors.value = {};
    thumbError.value = '';
});

function onNameInput() {
    // The slug always follows the name while creating; edits never touch it
    // (published URLs must stay stable).
    if (!isEditing.value) {
        form.value.slug = slugify(form.value.name);
    }
}

/** ToggleGroup allows deselect-to-empty; these options are mandatory, so ignore it. */
function setStatus(value) {
    if (value) form.value.status = value;
}

function setSelectionMode(value) {
    if (value) form.value.selection_mode = value;
}

function setType(value) {
    if (value) {
        form.value.type = value;
        if (value === 'general') {
            form.value.level = '';
            form.value.locked_message = '';
        }
    }
}

async function save() {
    saving.value = true;
    formErrors.value = {};
    try {
        const payload = {
            name: form.value.name,
            tagline: form.value.tagline || null,
            description: form.value.description || null,
            status: form.value.status,
            selection_mode: form.value.selection_mode,
            type: form.value.type,
            level: form.value.type === 'affiliate_community' ? Number(form.value.level) : undefined,
            locked_message: form.value.locked_message || null,
        };
        const res = isEditing.value
            ? await programsApi.update(props.program.id, payload) // slug omitted: never changes once published
            : await programsApi.create({ ...payload, slug: form.value.slug });
        open.value = false;
        emit('saved', res.program);
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        formErrors.value = e.errors ?? {};
        if (!Object.keys(formErrors.value).length) formErrors.value = { name: [e.message ?? 'Gagal menyimpan.'] };
    } finally {
        saving.value = false;
    }
}

// Thumbnail: satu handler untuk klik-unggah dan drag-and-drop.
const thumbInput = ref(null);
const uploadingThumb = ref(false);
const previewOpen = ref(false);

async function handleThumbFile(file) {
    if (!file || !isEditing.value || uploadingThumb.value) return;
    thumbError.value = '';
    uploadingThumb.value = true;
    try {
        const res = await programsApi.uploadThumbnail(props.program.id, file);
        thumbnailUrl.value = res.program.thumbnail_url;
        emit('thumbnail-changed', res.program);
    } catch (e) {
        if (!e.sessionExpired) thumbError.value = e.errors?.thumbnail?.[0] ?? e.message;
    } finally {
        uploadingThumb.value = false;
    }
}

function onThumbInputChange(event) {
    handleThumbFile(event.target.files?.[0]);
    event.target.value = '';
}

function onThumbDrop(event) {
    handleThumbFile(event.dataTransfer?.files?.[0]);
}

async function removeThumbnail() {
    thumbError.value = '';
    previewOpen.value = false;
    try {
        const res = await programsApi.removeThumbnail(props.program.id);
        thumbnailUrl.value = null;
        emit('thumbnail-changed', res?.program ?? { ...props.program, thumbnail_url: null });
    } catch (e) {
        if (!e.sessionExpired) thumbError.value = e.message;
    }
}

// Lightbox pratinjau: Escape menutup; ikut tertutup saat dialognya ditutup.
function onPreviewKey(e) {
    if (e.key === 'Escape') {
        previewOpen.value = false;
    }
}

watch(previewOpen, (isOpen) => {
    if (isOpen) {
        document.addEventListener('keydown', onPreviewKey);
    } else {
        document.removeEventListener('keydown', onPreviewKey);
    }
});

onUnmounted(() => document.removeEventListener('keydown', onPreviewKey));
</script>

<template>
    <Dialog v-model:open="open" :title="isEditing ? 'Ubah Program' : 'Tambah Program'">
        <form class="flex flex-col gap-4" @submit.prevent="save">
            <!-- Thumbnail: banner 16:9 (rasio yang sama dengan kartu publik = pratinjau jujur). -->
            <div v-if="isEditing">
                <div v-if="thumbnailUrl" class="group relative aspect-video w-full overflow-hidden rounded-xl border border-border bg-muted">
                    <img
                        :src="thumbnailUrl"
                        alt="Thumbnail kelas"
                        class="h-full w-full cursor-zoom-in object-cover"
                        @click="previewOpen = true"
                    />
                    <div class="pointer-events-none absolute inset-x-0 bottom-0 flex items-center justify-between gap-2 bg-gradient-to-t from-black/60 to-transparent p-3 opacity-0 transition group-hover:opacity-100">
                        <span class="text-xs font-medium text-white/90">Klik gambar untuk memperbesar</span>
                        <div class="pointer-events-auto flex gap-2">
                            <Button type="button" variant="secondary" size="sm" :disabled="uploadingThumb" @click="thumbInput?.click()">
                                {{ uploadingThumb ? 'Mengunggah…' : 'Ganti' }}
                            </Button>
                            <Button type="button" variant="destructive" size="sm" @click="removeThumbnail">Hapus</Button>
                        </div>
                    </div>
                </div>
                <button
                    v-else
                    type="button"
                    :disabled="uploadingThumb"
                    class="flex aspect-video w-full flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed border-input text-muted-foreground transition hover:border-teal-600/50 hover:bg-accent/40 hover:text-foreground"
                    @click="thumbInput?.click()"
                    @dragover.prevent
                    @drop.prevent="onThumbDrop"
                >
                    <ImagePlus class="size-6" />
                    <span class="text-sm font-medium">{{ uploadingThumb ? 'Mengunggah…' : 'Klik atau seret gambar ke sini' }}</span>
                    <span class="text-xs">JPEG/PNG/WebP, maks 2 MB · rasio 16:9 dianjurkan</span>
                </button>
                <input ref="thumbInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onThumbInputChange" />
                <p class="mt-1.5 text-xs text-muted-foreground">Kosong = kelas memakai cover otomatis bermotif brand.</p>
                <p v-if="thumbError" class="mt-1 text-xs text-destructive">{{ thumbError }}</p>
            </div>
            <div>
                <Input v-model="form.name" placeholder="Nama program" @input="onNameInput" />
                <p class="mt-1.5 text-xs text-muted-foreground">
                    <code>/program/{{ previewSlug || '…' }}</code>
                    <span v-if="isEditing" class="ml-1">· URL tidak berubah saat nama diedit</span>
                </p>
                <p v-if="formErrors.name" class="mt-1 text-xs text-destructive">{{ formErrors.name[0] }}</p>
                <p v-if="formErrors.slug" class="mt-1 text-xs text-destructive">{{ formErrors.slug[0] }}</p>
            </div>
            <Input v-model="form.tagline" placeholder="Tagline singkat (opsional)" />
            <div>
                <textarea
                    v-model="form.description"
                    rows="4"
                    placeholder="Deskripsi program (opsional)"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                ></textarea>
                <p v-if="formErrors.description" class="mt-1 text-xs text-destructive">{{ formErrors.description[0] }}</p>
            </div>
            <div>
                <label class="text-xs text-muted-foreground">Status</label>
                <ToggleGroup
                    type="single"
                    variant="outline"
                    class="mt-1.5 w-full"
                    :model-value="form.status"
                    @update:model-value="setStatus"
                >
                    <ToggleGroupItem v-for="option in STATUS_OPTIONS" :key="option.value" :value="option.value" class="flex-1">
                        {{ option.label }}
                    </ToggleGroupItem>
                </ToggleGroup>
            </div>
            <div>
                <label class="text-xs text-muted-foreground">Mode seleksi</label>
                <ToggleGroup
                    type="single"
                    variant="outline"
                    class="mt-1.5 w-full"
                    :model-value="form.selection_mode"
                    @update:model-value="setSelectionMode"
                >
                    <ToggleGroupItem v-for="option in MODE_OPTIONS" :key="option.value" :value="option.value" class="flex-1">
                        {{ option.label }}
                    </ToggleGroupItem>
                </ToggleGroup>
            </div>
            <div>
                <label class="text-xs text-muted-foreground">Tipe</label>
                <ToggleGroup
                    type="single"
                    variant="outline"
                    class="mt-1.5 w-full"
                    :model-value="form.type"
                    @update:model-value="setType"
                >
                    <ToggleGroupItem v-for="option in TYPE_OPTIONS" :key="option.value" :value="option.value" class="flex-1">
                        {{ option.label }}
                    </ToggleGroupItem>
                </ToggleGroup>
            </div>
            <div v-if="form.type === 'affiliate_community'">
                <label class="text-xs text-muted-foreground">Level</label>
                <Input v-model="form.level" type="number" min="1" max="255" placeholder="1" class="mt-1.5" />
                <p v-if="formErrors.level" class="mt-1 text-xs text-destructive">{{ formErrors.level[0] }}</p>
            </div>
            <div v-if="form.type === 'affiliate_community'">
                <label class="text-xs text-muted-foreground">Pesan terkunci (opsional)</label>
                <textarea
                    v-model="form.locked_message"
                    rows="3"
                    placeholder="Kosongkan untuk memakai pesan default."
                    class="mt-1.5 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                ></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <Button type="button" variant="outline" size="sm" @click="open = false">Batal</Button>
                <Button type="submit" size="sm" :disabled="saving">{{ saving ? 'Menyimpan…' : 'Simpan' }}</Button>
            </div>
        </form>
    </Dialog>

    <!-- Lightbox pratinjau thumbnail: klik area mana pun atau Escape untuk menutup. -->
    <Teleport to="body">
        <div
            v-if="previewOpen && thumbnailUrl"
            class="fixed inset-0 z-[70] flex cursor-zoom-out items-center justify-center bg-black/80 p-6 backdrop-blur-sm"
            @click="previewOpen = false"
        >
            <img
                :src="thumbnailUrl"
                alt="Pratinjau thumbnail"
                class="max-h-[85vh] max-w-[92vw] rounded-lg object-contain shadow-2xl"
            />
        </div>
    </Teleport>
</template>
