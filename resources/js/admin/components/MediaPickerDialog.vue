<script setup>
import { ref, watch } from 'vue';
import { Dialog } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import MediaGrid from '@/components/MediaGrid.vue';

const open = defineModel('open', { type: Boolean, default: false });

const emit = defineEmits(['picked']);

// Select-then-confirm: clicking a tile only highlights it; the footer button
// does the insert, so a stray click never drops an image into the content.
const chosen = ref(null);

watch(open, (isOpen) => {
    if (isOpen) chosen.value = null;
});

function insert() {
    if (!chosen.value) return;
    emit('picked', chosen.value);
    open.value = false;
}
</script>

<template>
    <Dialog v-model:open="open" wide title="Sisipkan gambar">
        <p class="text-sm text-muted-foreground">
            Pilih dari media, atau unggah yang baru. Gambar masuk tepat di posisi kursormu.
        </p>
        <div class="mt-3">
            <MediaGrid picker @select="chosen = $event" />
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-border pt-4">
            <p class="min-w-0 flex-1 truncate text-xs text-muted-foreground">
                <template v-if="chosen">
                    Terpilih: <b class="text-foreground">{{ chosen.original_name }}</b>
                    <template v-if="chosen.alt_text"> · alt: "{{ chosen.alt_text }}"</template>
                </template>
                <template v-else>Klik salah satu gambar untuk memilihnya.</template>
            </p>
            <Button variant="ghost" @click="open = false">Batal</Button>
            <Button :disabled="!chosen" @click="insert">Sisipkan gambar</Button>
        </div>
    </Dialog>
</template>
