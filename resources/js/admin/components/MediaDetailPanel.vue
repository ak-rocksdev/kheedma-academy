<script setup>
import { ref, watch } from 'vue';
import { Copy, Check, Trash2, ExternalLink } from 'lucide-vue-next';
import { media as mediaApi } from '@/api';
import { copyText } from '@/lib/clipboard';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

/**
 * Right-hand detail panel of the media manager: everything about one file in
 * one place — large preview (image, or embedded PDF), auto-saving alt text,
 * copy-link as the primary action, where the file is used, and delete.
 */
const props = defineProps({
    /** Media item (list payload shape) currently selected in the grid. */
    item: { type: Object, required: true },
});

const emit = defineEmits(['saved', 'deleted']);

const altText = ref('');
const altSaved = ref(false);
const usedIn = ref(null); // null = loading, [] = unused
const copied = ref(false);
const armedDelete = ref(false);
const error = ref('');

// Every selection change re-seeds the panel and fetches where the file is used.
watch(() => props.item.id, loadDetail, { immediate: true });

async function loadDetail() {
    altText.value = props.item.alt_text ?? '';
    altSaved.value = false;
    copied.value = false;
    armedDelete.value = false;
    error.value = '';
    usedIn.value = null;
    try {
        const { media: detail } = await mediaApi.show(props.item.id);
        if (detail.id === props.item.id) usedIn.value = detail.used_in;
    } catch (e) {
        if (!e.sessionExpired) usedIn.value = [];
    }
}

async function saveAlt() {
    if ((props.item.alt_text ?? '') === altText.value) return;
    error.value = '';
    try {
        const { media: updated } = await mediaApi.update(props.item.id, { alt_text: altText.value });
        emit('saved', updated);
        altSaved.value = true;
        setTimeout(() => (altSaved.value = false), 2000);
    } catch (e) {
        if (!e.sessionExpired) error.value = e.errors?.alt_text?.[0] ?? e.message ?? 'Gagal menyimpan teks alternatif.';
    }
}

// Counter keys the burst ring so repeat copies replay the animation.
const copyBurst = ref(0);

async function copyLink() {
    error.value = '';
    const ok = await copyText(window.location.origin + props.item.url);
    if (!ok) {
        error.value = 'Gagal menyalin link. Salin manual: ' + props.item.url;
        return;
    }
    copied.value = true;
    copyBurst.value++;
    setTimeout(() => (copied.value = false), 1800);
}

async function removeFile() {
    if (!armedDelete.value) {
        armedDelete.value = true;
        return;
    }
    error.value = '';
    try {
        await mediaApi.remove(props.item.id);
        emit('deleted', props.item.id);
    } catch (e) {
        if (!e.sessionExpired) error.value = e.message ?? 'Gagal menghapus file.';
    } finally {
        armedDelete.value = false;
    }
}

function formatSize(bytes) {
    return bytes >= 1_048_576 ? `${(bytes / 1_048_576).toFixed(1)} MB` : `${Math.round(bytes / 1024)} KB`;
}

function formatDate(iso) {
    return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
}
</script>

<template>
    <aside class="sticky top-4 rounded-xl border border-border bg-card p-4">
        <!-- Preview on a "mat", like a printed photo laid on the desk. -->
        <div class="rounded-lg bg-secondary p-3">
            <img
                v-if="item.is_image"
                :src="item.url"
                :alt="altText || item.original_name"
                class="max-h-64 w-full rounded object-contain shadow-md"
            >
            <object v-else type="application/pdf" :data="item.url" class="h-64 w-full rounded bg-white shadow-md">
                <p class="p-4 text-sm text-muted-foreground">
                    Preview PDF tidak tersedia di browser ini.
                </p>
            </object>
        </div>
        <a
            v-if="!item.is_image"
            :href="item.url"
            target="_blank"
            rel="noopener"
            class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline"
        >
            <ExternalLink class="h-3.5 w-3.5" /> Buka PDF di tab baru
        </a>

        <p class="mt-3 break-all text-sm font-semibold" :title="item.original_name">{{ item.original_name }}</p>
        <p class="mt-0.5 text-xs text-muted-foreground">
            {{ item.is_image ? 'Foto' : 'Dokumen' }} · {{ formatSize(item.size) }} · diunggah {{ formatDate(item.created_at) }}
        </p>

        <div v-if="item.is_image" class="mt-4">
            <label :for="`alt-${item.id}`" class="mb-1.5 block text-xs font-semibold text-foreground/80">Teks alternatif</label>
            <Input :id="`alt-${item.id}`" v-model="altText" placeholder="Jelaskan isi foto ini…" @change="saveAlt" />
            <p class="mt-1 text-[11px] text-muted-foreground">
                {{ altSaved ? 'Tersimpan.' : 'Tersimpan otomatis. Membantu pembaca layar memahami foto ini.' }}
            </p>
        </div>

        <div class="relative mt-4">
            <Button class="w-full" :class="copied && 'bg-teal-500 hover:bg-teal-500'" @click="copyLink">
                <Check v-if="copied" class="mr-1.5 h-4 w-4" />
                <Copy v-else class="mr-1.5 h-4 w-4" />
                {{ copied ? 'Link tersalin!' : 'Salin link' }}
            </Button>
            <span v-if="copyBurst" :key="copyBurst" class="kh-copy-burst" aria-hidden="true"></span>
        </div>

        <div v-if="usedIn && usedIn.length" class="mt-3 rounded-lg bg-accent px-3 py-2.5 text-xs leading-relaxed text-accent-foreground">
            Dipakai di: <b>{{ usedIn.join(', ') }}</b>
        </div>
        <p v-else-if="usedIn" class="mt-3 text-xs text-muted-foreground">Belum dipakai di konten mana pun.</p>

        <p v-if="error" class="mt-3 text-xs text-destructive">{{ error }}</p>

        <Button variant="ghost" class="mt-2 w-full text-destructive hover:bg-destructive/10 hover:text-destructive" @click="removeFile">
            <Trash2 class="mr-1.5 h-4 w-4" />
            {{ armedDelete ? 'Klik sekali lagi untuk menghapus' : 'Hapus file ini' }}
        </Button>
    </aside>
</template>
