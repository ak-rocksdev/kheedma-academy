<script setup>
import { ref, watch, onMounted } from 'vue';
import { UploadCloud, Copy, Check, Trash2, FileText, Pencil } from 'lucide-vue-next';
import { media as mediaApi } from '@/api';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Alert } from '@/components/ui/alert';

const props = defineProps({
    /** Picker mode: images only, click = select, no destructive actions. */
    picker: { type: Boolean, default: false },
});

const emit = defineEmits(['select']);

const items = ref([]);
const meta = ref(null);
const search = ref('');
const loading = ref(false);
const uploading = ref(false);
const error = ref('');
const copiedId = ref(null);

async function load(page = 1) {
    loading.value = true;
    error.value = '';
    try {
        const params = new URLSearchParams();
        params.set('page', page);
        if (props.picker) params.set('type', 'image');
        if (search.value) params.set('search', search.value);
        const response = await mediaApi.list(`?${params.toString()}`);
        items.value = page === 1 ? response.data : [...items.value, ...response.data];
        meta.value = { current: response.current_page, last: response.last_page };
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        error.value = e.message ?? 'Gagal memuat file.';
    } finally {
        loading.value = false;
    }
}

let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => load(1), 300);
});

onMounted(() => load(1));

async function onFilesChosen(fileList) {
    error.value = '';
    uploading.value = true;
    let uploadError = '';
    try {
        for (const file of fileList) {
            await mediaApi.upload(file);
        }
    } catch (e) {
        if (!e.sessionExpired) uploadError = e.errors?.file?.[0] ?? e.message ?? 'Gagal mengunggah file.';
    } finally {
        uploading.value = false;
        // Refresh even after a mid-batch failure so the files that did make
        // it show up alongside the error (load() clears error.value, so the
        // upload error is re-applied after the refresh).
        await load(1);
        if (uploadError) error.value = uploadError;
    }
}

function onDrop(event) {
    if (event.dataTransfer?.files?.length) onFilesChosen(event.dataTransfer.files);
}

async function copyLink(item) {
    await navigator.clipboard.writeText(window.location.origin + item.url);
    copiedId.value = item.id;
    setTimeout(() => (copiedId.value = null), 1500);
}

async function editAlt(item) {
    const alt = window.prompt('Teks alternatif gambar (untuk aksesibilitas):', item.alt_text ?? '');
    if (alt === null) return;
    try {
        const { media: updated } = await mediaApi.update(item.id, { alt_text: alt });
        Object.assign(item, updated);
    } catch (e) {
        if (!e.sessionExpired) error.value = e.message ?? 'Gagal menyimpan teks alternatif.';
    }
}

const deletingItem = ref(null);
async function confirmDelete(item) {
    error.value = '';
    try {
        await mediaApi.remove(item.id);
        items.value = items.value.filter((m) => m.id !== item.id);
    } catch (e) {
        if (!e.sessionExpired) error.value = e.message ?? 'Gagal menghapus file.';
    } finally {
        deletingItem.value = null;
    }
}

function formatSize(bytes) {
    return bytes >= 1_048_576 ? `${(bytes / 1_048_576).toFixed(1)} MB` : `${Math.round(bytes / 1024)} KB`;
}
</script>

<template>
    <div @dragover.prevent @drop.prevent="onDrop">
        <div class="flex flex-wrap items-center gap-3">
            <Input v-model="search" placeholder="Cari nama file…" class="max-w-xs" />
            <label class="inline-flex cursor-pointer items-center gap-2 rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground hover:opacity-90">
                <UploadCloud class="h-4 w-4" />
                {{ uploading ? 'Mengunggah…' : 'Unggah File' }}
                <input
                    type="file"
                    class="sr-only"
                    multiple
                    :accept="picker ? 'image/*' : 'image/*,application/pdf'"
                    :disabled="uploading"
                    @change="onFilesChosen($event.target.files); $event.target.value = ''"
                >
            </label>
            <p class="text-xs text-muted-foreground">Bisa juga seret dan lepas file ke sini. Maks 5 MB per file.</p>
        </div>

        <Alert v-if="error" class="mt-3 px-3.5 py-2.5">{{ error }}</Alert>

        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <div
                v-for="item in items"
                :key="item.id"
                class="group overflow-hidden rounded-lg border bg-card"
                :class="picker && 'cursor-pointer transition hover:ring-2 hover:ring-ring'"
                @click="picker && emit('select', item)"
            >
                <img v-if="item.is_image" :src="item.url" :alt="item.alt_text ?? item.original_name" class="aspect-square w-full object-cover">
                <div v-else class="flex aspect-square w-full items-center justify-center bg-secondary">
                    <FileText class="h-10 w-10 text-muted-foreground" />
                </div>
                <div class="p-2">
                    <p class="truncate text-xs font-medium" :title="item.original_name">{{ item.original_name }}</p>
                    <p class="text-xs text-muted-foreground">{{ formatSize(item.size) }}</p>
                    <div v-if="!picker" class="mt-1.5 flex gap-1">
                        <Button variant="ghost" size="icon" class="h-7 w-7" :title="copiedId === item.id ? 'Tersalin!' : 'Salin link'" @click="copyLink(item)">
                            <Check v-if="copiedId === item.id" class="h-3.5 w-3.5 text-teal-600" />
                            <Copy v-else class="h-3.5 w-3.5" />
                        </Button>
                        <Button v-if="item.is_image" variant="ghost" size="icon" class="h-7 w-7" title="Ubah teks alternatif" @click="editAlt(item)">
                            <Pencil class="h-3.5 w-3.5" />
                        </Button>
                        <Button
                            variant="ghost" size="icon" class="h-7 w-7 text-destructive"
                            :title="deletingItem === item.id ? 'Klik lagi untuk hapus' : 'Hapus'"
                            @click="deletingItem === item.id ? confirmDelete(item) : (deletingItem = item.id)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <p v-if="!loading && items.length === 0" class="mt-8 text-center text-sm text-muted-foreground">
            Belum ada file. Unggah file pertamamu di atas.
        </p>

        <div v-if="meta && meta.current < meta.last" class="mt-4 text-center">
            <Button variant="outline" :disabled="loading" @click="load(meta.current + 1)">Muat lebih banyak</Button>
        </div>
    </div>
</template>
