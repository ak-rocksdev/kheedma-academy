<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { UploadCloud, FileText, ImagePlus } from 'lucide-vue-next';
import { media as mediaApi } from '@/api';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Alert } from '@/components/ui/alert';
import MediaDetailPanel from '@/components/MediaDetailPanel.vue';
import { useMediaUpload } from '@/composables/useMediaUpload';

/**
 * Media manager, gallery + detail-panel layout: the grid is for finding a
 * file, the panel is where everything about it happens. In picker mode the
 * panel is dropped and clicking a tile only selects — the surrounding dialog
 * confirms the insert.
 */
const props = defineProps({
    /** Picker mode: images only, click = select for the parent to confirm. */
    picker: { type: Boolean, default: false },
});

const emit = defineEmits(['select']);

const items = ref([]);
const meta = ref(null);
const search = ref('');
const loading = ref(false);
const error = ref('');
const selectedId = ref(null);

const { uploading, progress: uploadProgress, uploadFiles } = useMediaUpload();

const selectedItem = computed(() => items.value.find((m) => m.id === selectedId.value) ?? null);

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
        // Keep the panel meaningful: reselect if the selection scrolled out of
        // the result set, defaulting to the newest file.
        if (!props.picker && !selectedItem.value) selectedId.value = items.value[0]?.id ?? null;
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

function selectItem(item) {
    selectedId.value = item.id;
    if (props.picker) emit('select', item);
}

async function onFilesChosen(fileList) {
    error.value = '';
    const { lastUploadedId, errorMessage } = await uploadFiles(fileList);
    // Refresh even after a mid-batch failure so the files that did make it
    // show up alongside the error (load() clears error.value, so the upload
    // error is re-applied after the refresh).
    await load(1);
    if (lastUploadedId) {
        selectedId.value = lastUploadedId;
        // In picker mode a fresh upload IS the user's choice — hand it to the
        // dialog so "Sisipkan gambar" is ready without an extra click.
        if (props.picker && selectedItem.value) emit('select', selectedItem.value);
    }
    if (errorMessage) error.value = errorMessage;
}

function onDrop(event) {
    if (event.dataTransfer?.files?.length) onFilesChosen(event.dataTransfer.files);
}

function onPanelSaved(updated) {
    const item = items.value.find((m) => m.id === updated.id);
    if (item) Object.assign(item, updated);
}

function onPanelDeleted(id) {
    items.value = items.value.filter((m) => m.id !== id);
    selectedId.value = items.value[0]?.id ?? null;
}

function formatSize(bytes) {
    return bytes >= 1_048_576 ? `${(bytes / 1_048_576).toFixed(1)} MB` : `${Math.round(bytes / 1024)} KB`;
}
</script>

<template>
    <div @dragover.prevent @drop.prevent="onDrop">
        <!-- Upload strip: the one entry point, with drag-drop over the whole page. -->
        <div class="flex flex-wrap items-center gap-3 rounded-xl border-2 border-dashed border-ring/50 bg-card px-4 py-3">
            <label class="inline-flex cursor-pointer items-center gap-2 rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-orange-600">
                <UploadCloud class="h-4 w-4" />
                {{ uploading ? 'Mengunggah…' : 'Unggah file' }}
                <input
                    type="file"
                    class="sr-only"
                    multiple
                    :accept="picker ? 'image/*' : 'image/*,application/pdf'"
                    :disabled="uploading"
                    @change="onFilesChosen($event.target.files); $event.target.value = ''"
                >
            </label>
            <p class="text-sm text-foreground/80">atau <b>seret dan lepas</b> {{ picker ? 'ke area dialog ini' : 'ke mana saja di halaman ini' }}</p>
            <p class="ml-auto text-xs text-muted-foreground">JPG · PNG · WebP · GIF{{ picker ? '' : ' · PDF' }} · maks 5 MB</p>

            <div
                v-if="uploadProgress"
                class="flex basis-full items-center gap-3 pt-1"
                role="progressbar"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-valuenow="uploadProgress.percent"
                :aria-label="`Mengunggah ${uploadProgress.name}`"
            >
                <p class="min-w-0 flex-1 truncate text-xs text-muted-foreground">
                    Mengunggah <b class="text-foreground">{{ uploadProgress.name }}</b>
                    <template v-if="uploadProgress.count > 1"> (file {{ uploadProgress.index }} dari {{ uploadProgress.count }})</template>
                </p>
                <div class="h-2 w-40 overflow-hidden rounded-full bg-secondary sm:w-64">
                    <div
                        class="h-full rounded-full bg-orange-500 transition-[width] duration-150"
                        :style="{ width: uploadProgress.percent + '%' }"
                    ></div>
                </div>
                <span class="w-11 text-right text-sm font-bold tabular-nums text-foreground">{{ uploadProgress.percent }}%</span>
            </div>
        </div>

        <div class="mt-3 max-w-xs">
            <Input v-model="search" placeholder="Cari nama file…" />
        </div>

        <Alert v-if="error" class="mt-3 px-3.5 py-2.5">{{ error }}</Alert>

        <div class="mt-4" :class="!picker && selectedItem && 'grid items-start gap-5 lg:grid-cols-[1fr_320px]'">
            <div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3" :class="picker ? 'md:grid-cols-4' : 'xl:grid-cols-4'">
                    <button
                        v-for="item in items"
                        :key="item.id"
                        type="button"
                        class="group overflow-hidden rounded-lg border bg-card text-left transition hover:shadow-md"
                        :class="selectedId === item.id ? 'ring-2 ring-ring ring-offset-2 ring-offset-background' : ''"
                        @click="selectItem(item)"
                    >
                        <img v-if="item.is_image" :src="item.url" :alt="item.alt_text ?? item.original_name" class="aspect-square w-full object-cover">
                        <div v-else class="flex aspect-square w-full items-center justify-center bg-secondary">
                            <FileText class="h-10 w-10 text-muted-foreground" />
                        </div>
                        <div class="px-2.5 py-2">
                            <p class="truncate text-xs font-medium" :title="item.original_name">{{ item.original_name }}</p>
                            <p class="text-[11px] text-muted-foreground">{{ formatSize(item.size) }}</p>
                        </div>
                    </button>
                </div>

                <!-- Empty state: a bounded card that doubles as an obvious drop
                     target (the whole page already accepts drops). -->
                <div
                    v-if="!loading && items.length === 0"
                    class="mt-6 flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card px-6 py-14 text-center"
                >
                    <span class="flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br from-teal-100 via-sand-50 to-orange-200/70 ring-1 ring-inset ring-teal-900/10">
                        <ImagePlus class="size-7 text-teal-700" />
                    </span>
                    <p class="mt-4 text-sm font-semibold text-foreground">Belum ada file media</p>
                    <p class="mt-1 max-w-xs text-sm text-muted-foreground">
                        Unggah lewat tombol di atas, atau seret dan lepas file langsung ke sini.
                    </p>
                </div>

                <div v-if="meta && meta.current < meta.last" class="mt-4 text-center">
                    <Button variant="outline" :disabled="loading" @click="load(meta.current + 1)">Muat lebih banyak</Button>
                </div>
            </div>

            <MediaDetailPanel
                v-if="!picker && selectedItem"
                :item="selectedItem"
                @saved="onPanelSaved"
                @deleted="onPanelDeleted"
            />
        </div>
    </div>
</template>
