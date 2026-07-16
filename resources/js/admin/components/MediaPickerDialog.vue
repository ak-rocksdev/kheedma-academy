<script setup>
import { Dialog } from '@/components/ui/dialog';
import MediaGrid from '@/components/MediaGrid.vue';

const open = defineModel('open', { type: Boolean, default: false });

const emit = defineEmits(['picked']);

function onSelect(item) {
    emit('picked', item);
    open.value = false;
}
</script>

<template>
    <Dialog v-model:open="open" title="Pilih Gambar">
        <p class="text-sm text-muted-foreground">Klik gambar untuk menyisipkannya ke konten.</p>
        <div class="mt-3">
            <MediaGrid picker @select="onSelect" />
        </div>
    </Dialog>
</template>
